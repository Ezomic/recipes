<?php

declare(strict_types=1);

use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travel;

beforeEach(function () {
    fakeZero();
    config(['app.name' => 'Recipes']);
});

it('renders the login page without a code step first', function () {
    get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('auth/Login')
        ->where('email', null)
        ->missing('canResetPassword'));
});

it('mails a six digit code through Zero and moves to the code step', function () {
    $user = User::factory()->create(['email' => 'robbin@example.com']);

    requestLoginCode('robbin@example.com')
        ->assertRedirect(route('login'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('login_code_email', 'robbin@example.com');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://zero.test/api/outbound'
        && $request->hasHeader('Authorization', 'Bearer zob_test_token_value')
        && $request['to'] === 'robbin@example.com'
        && $request['subject'] === 'Je inlogcode voor Recipes'
        && str_contains($request['text'], '10 minuten')
        && str_contains($request['text'], 'negeren')
        && preg_match('/\b\d{6}\b/', $request['text']) === 1);

    $record = LoginCode::query()->where('user_id', $user->id)->sole();

    expect(sentLoginCode())->toMatch('/^\d{6}$/')
        ->and($record->code_hash)->not->toContain(sentLoginCode())
        ->and($record->attempts)->toBe(0)
        ->and(abs($record->expires_at->diffInSeconds(now()->addMinutes(10), true)))->toBeLessThan(30);
});

it('shows the login page the normalised address after asking for a code', function () {
    User::factory()->create(['email' => 'robbin@example.com']);

    requestLoginCode("  Robbin@Example.COM \u{00A0}\u{200B}");
    get(route('login'))->assertInertia(fn (Assert $page) => $page->where('email', 'robbin@example.com'));
});

it('never returns the code in a response or a url', function () {
    User::factory()->create(['email' => 'robbin@example.com']);

    $response = requestLoginCode('robbin@example.com');
    $code = sentLoginCode();

    expect($response->headers->get('Location'))->not->toContain($code)
        ->and($response->getContent())->not->toContain($code);

    $page = get(route('login'));

    expect($page->getContent())->not->toContain($code);
});

it('answers an unknown address exactly like a known one and sends nothing', function () {
    User::factory()->create(['email' => 'robbin@example.com']);

    $known = requestLoginCode('robbin@example.com');
    $unknown = requestLoginCode('stranger@example.com');

    expect($unknown->getStatusCode())->toBe($known->getStatusCode())
        ->and($unknown->headers->get('Location'))->toBe($known->headers->get('Location'));

    $unknown->assertSessionHasNoErrors()->assertSessionHas('login_code_email', 'stranger@example.com');
    Http::assertSentCount(1);
    expect(LoginCode::query()->count())->toBe(1);
});

it('does not create a code for an unknown address', function () {
    requestLoginCode('stranger@example.com')->assertSessionHasNoErrors();

    Http::assertNothingSent();
    expect(LoginCode::query()->count())->toBe(0);
});

it('normalises case, spaces and invisible characters around the address before the lookup', function (string $raw) {
    User::factory()->create(['email' => 'robbin@example.com']);

    requestLoginCode($raw)->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request): bool => $request['to'] === 'robbin@example.com');
})->with([
    'uppercase' => ['ROBBIN@EXAMPLE.COM'],
    'padded' => ['   robbin@example.com   '],
    'nbsp' => ["\u{00A0}robbin@example.com\u{00A0}"],
    'zero width' => ["\u{200B}robbin@example.com\u{FEFF}"],
]);

it('only ever mails the stored address, never the typed one', function () {
    User::factory()->create(['email' => 'robbin@example.com']);

    requestLoginCode('ROBBIN@example.com');

    Http::assertSent(fn (Request $request): bool => $request['to'] === 'robbin@example.com');
});

it('rejects malformed addresses without looking anything up or sending anything', function (mixed $email) {
    User::factory()->create(['email' => 'robbin@example.com']);

    requestLoginCode($email)->assertSessionHasErrors('email');

    Http::assertNothingSent();
    expect(LoginCode::query()->count())->toBe(0);
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'zero width only' => ["\u{200B}\u{200B}"],
    'no at sign' => ['robbin.example.com'],
    'newline injection' => ["robbin@example.com\nBcc: evil@example.com"],
    'carriage return injection' => ["robbin@example.com\r\nBcc: evil@example.com"],
    'inner newline' => ["rob\nbin@example.com"],
    'nul byte' => ["robbin\0@example.com"],
    'very long' => [str_repeat('a', 300).'@example.com'],
    'array' => [['robbin@example.com']],
    'nested array' => [[['robbin@example.com']]],
    'integer' => [12345],
    'boolean' => [true],
    'null' => [null],
]);

it('rejects a request with no email field at all', function () {
    post(route('login.code.send'))->assertSessionHasErrors('email');

    Http::assertNothingSent();
});

it('replaces the previous code when a new one is issued, and the old one stops working', function () {
    $user = User::factory()->create(['email' => 'robbin@example.com']);

    requestLoginCode('robbin@example.com');
    $old = sentLoginCode();
    travel(61)->seconds();
    requestLoginCode('robbin@example.com');
    $new = sentLoginCode();

    expect(LoginCode::query()->where('user_id', $user->id)->count())->toBe(1);

    post(route('login.store'), ['email' => 'robbin@example.com', 'code' => $old === $new ? '000000' : $old])
        ->assertSessionHasErrors('code');
    $this->assertGuest();

    post(route('login.store'), ['email' => 'robbin@example.com', 'code' => $new])->assertRedirect(route('recipes.index'));
    $this->assertAuthenticated();
});

it('allows one new code a minute per address and shows the wait', function () {
    User::factory()->create(['email' => 'robbin@example.com']);

    requestLoginCode('robbin@example.com')->assertSessionHasNoErrors();
    $first = sentLoginCode();

    requestLoginCode('robbin@example.com')->assertSessionHasErrors('email');
    expect(firstError('email'))->toContain('seconden');

    Http::assertSentCount(1);
    expect(sentLoginCode())->toBe($first);

    travel(61)->seconds();
    requestLoginCode('robbin@example.com')->assertSessionHasNoErrors();
    Http::assertSentCount(2);
});

it('counts a different spelling of the address against the same limit', function () {
    User::factory()->create(['email' => 'robbin@example.com']);

    requestLoginCode('robbin@example.com');
    requestLoginCode('ROBBIN@example.com ')->assertSessionHasErrors('email');

    Http::assertSentCount(1);
});

it('limits a known address to five codes an hour', function () {
    User::factory()->create(['email' => 'robbin@example.com']);

    foreach (range(1, 5) as $ignored) {
        requestLoginCode('robbin@example.com')->assertSessionHasNoErrors();
        travel(61)->seconds();
    }

    requestLoginCode('robbin@example.com')->assertSessionHasErrors('email');
    Http::assertSentCount(5);

    travel(61)->minutes();
    requestLoginCode('robbin@example.com')->assertSessionHasNoErrors();
});

it('limits an unknown address the same way, so the limits reveal nothing', function () {
    requestLoginCode('stranger@example.com')->assertSessionHasNoErrors();
    $second = requestLoginCode('stranger@example.com');

    $second->assertSessionHasErrors('email');
    expect(firstError('email'))->toContain('seconden');
});

it('limits how many codes one address on the network may ask for in an hour', function () {
    foreach (range(1, 10) as $number) {
        requestLoginCode("someone{$number}@example.com")->assertSessionHasNoErrors();
    }

    requestLoginCode('someone11@example.com')->assertSessionHasErrors('email');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
        ->post(route('login.code.send'), ['email' => 'someone12@example.com'])
        ->assertSessionHasNoErrors();
});

it('keeps a user who is already logged in out of the code flow', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('login.code.send'), ['email' => $user->email])
        ->assertRedirect();

    Http::assertNothingSent();
    expect(LoginCode::query()->count())->toBe(0);
});

it('shows a Dutch error and keeps no code when Zero answers with an error', function (int $status) {
    $user = User::factory()->create(['email' => 'robbin@example.com']);
    fakeZero($status);

    requestLoginCode('robbin@example.com')
        ->assertSessionHasErrors(['email' => 'De code kon niet worden verstuurd, probeer het later opnieuw.'])
        ->assertSessionMissing('login_code_email');

    expect(LoginCode::query()->where('user_id', $user->id)->count())->toBe(0);
})->with([401, 422, 429, 500, 503, 200]);

it('shows the same Dutch error and keeps no code when Zero is unreachable', function () {
    $user = User::factory()->create(['email' => 'robbin@example.com']);
    fakeZero(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    requestLoginCode('robbin@example.com')
        ->assertSessionHasErrors(['email' => 'De code kon niet worden verstuurd, probeer het later opnieuw.']);

    expect(LoginCode::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('keeps no usable code at all when a replacement fails to send', function () {
    $user = User::factory()->create(['email' => 'robbin@example.com']);

    requestLoginCode('robbin@example.com');
    travel(61)->seconds();
    fakeZero(500);
    requestLoginCode('robbin@example.com')->assertSessionHasErrors('email');

    expect(LoginCode::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('fails safe when Zero is not configured', function (?string $url, ?string $token) {
    $user = User::factory()->create(['email' => 'robbin@example.com']);
    config(['services.zero.url' => $url, 'services.zero.token' => $token]);

    requestLoginCode('robbin@example.com')->assertSessionHasErrors('email');

    Http::assertNothingSent();
    expect(LoginCode::query()->where('user_id', $user->id)->count())->toBe(0);
})->with([
    'no url' => [null, 'zob_x'],
    'no token' => ['https://zero.test', null],
    'empty' => ['', ''],
]);

it('never writes the code or the token to a log, on success or failure', function () {
    User::factory()->create(['email' => 'robbin@example.com']);
    $logs = captureLogs();

    requestLoginCode('robbin@example.com');
    $code = sentLoginCode();
    travel(61)->seconds();
    fakeZero(500);
    requestLoginCode('robbin@example.com');
    travel(61)->seconds();
    fakeZero(fn () => throw new ConnectionException('timed out'));
    requestLoginCode('robbin@example.com');
    post(route('login.store'), ['email' => 'robbin@example.com', 'code' => '000000']);

    expect($logs())->not->toContain($code)
        ->not->toContain('zob_test_token_value')
        ->not->toContain('Bearer');
});

it('logs that Zero failed, without the body', function () {
    User::factory()->create(['email' => 'robbin@example.com']);
    $logs = captureLogs();
    fakeZero(fn () => Http::response(['message' => 'secret detail robbin@example.com'], 500));

    requestLoginCode('robbin@example.com');

    expect($logs())->toContain('Zero')->toContain('500')->not->toContain('secret detail');
});

it('has no password login or reset routes any more', function () {
    expect(Route::has('password.request'))->toBeFalse()
        ->and(Route::has('password.email'))->toBeFalse()
        ->and(Route::has('password.reset'))->toBeFalse()
        ->and(Route::has('two-factor.login'))->toBeFalse()
        ->and(Route::has('user-password.update'))->toBeFalse();

    get('/forgot-password')->assertNotFound();
    get('/two-factor-challenge')->assertNotFound();
});

it('does not accept a password as a way in', function () {
    $user = User::factory()->create();

    post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('code');

    $this->assertGuest();
});
