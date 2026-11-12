<?php

declare(strict_types=1);

use App\Actions\Auth\VerifyLoginCode;
use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travel;

function verifyCode(mixed $code, mixed $email = 'robbin@example.com', array $extra = []): TestResponse
{
    return post(route('login.store'), ['email' => $email, 'code' => $code, ...$extra]);
}

function issueCode(string $email = 'robbin@example.com'): string
{
    requestLoginCode($email);

    return sentLoginCode();
}

function wrongCode(string $right): string
{
    return $right === '123456' ? '654321' : '123456';
}

beforeEach(function () {
    fakeZero();
    $this->user = User::factory()->create(['email' => 'robbin@example.com']);
});

it('logs in with the right code and lands on the recipe list', function () {
    $code = issueCode();

    verifyCode($code)->assertRedirect(route('recipes.index'))->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($this->user);
    expect(LoginCode::query()->count())->toBe(0);
});

it('accepts the address in another case or with padding', function () {
    $code = issueCode();

    verifyCode($code, "  ROBBIN@Example.com\u{00A0}")->assertRedirect(route('recipes.index'));
});

it('returns to the page the visitor first asked for', function () {
    get(route('recipes.create'))->assertRedirect(route('login'));
    $code = issueCode();

    verifyCode($code)->assertRedirect(route('recipes.create'));
});

it('regenerates the session id on login and drops the code step state', function () {
    $code = issueCode();
    $before = session()->getId();

    verifyCode($code);

    expect(session()->getId())->not->toBe($before)
        ->and(session()->has('login_code_email'))->toBeFalse();
});

it('sets a remember cookie only when asked to', function () {
    $code = issueCode();
    $plain = verifyCode($code);

    expect($plain->getCookie(Auth::guard()->getRecallerName()))->toBeNull();

    Auth::guard()->logout();
    $this->flushSession();
    travel(61)->seconds();
    $code = issueCode();

    $remembered = verifyCode($code, extra: ['remember' => '1']);

    expect($remembered->getCookie(Auth::guard()->getRecallerName()))->not->toBeNull();
});

it('logs in and remembers when the ticked checkbox posts its value', function (mixed $ticked) {
    $code = issueCode();

    $response = verifyCode($code, extra: ['remember' => $ticked])
        ->assertRedirect(route('recipes.index'))
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($this->user);
    expect($response->getCookie(Auth::guard()->getRecallerName()))->not->toBeNull();
})->with([
    'browser default' => ['on'],
    'one' => ['1'],
    'true' => [true],
]);

it('logs in without remembering when the checkbox is left alone', function (array $extra) {
    $code = issueCode();

    $response = verifyCode($code, extra: $extra)
        ->assertRedirect(route('recipes.index'))
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($this->user);
    expect($response->getCookie(Auth::guard()->getRecallerName()))->toBeNull();
})->with([
    'absent' => [[]],
    'zero' => [['remember' => '0']],
    'false' => [['remember' => false]],
    'empty' => [['remember' => '']],
]);

it('accepts codes with leading zeros', function () {
    $this->user->loginCode()->create([
        'code_hash' => hash_hmac('sha256', $this->user->id.'|001234', (string) config('app.key')),
        'expires_at' => now()->addMinutes(10),
    ]);

    verifyCode('001234')->assertRedirect(route('recipes.index'));
});

it('accepts the code with the invisible padding a paste can add', function () {
    $code = issueCode();

    verifyCode("\u{00A0}{$code}\u{200B}")->assertRedirect(route('recipes.index'));
});

it('refuses a wrong code with a Dutch error and counts the attempt', function () {
    $code = issueCode();

    verifyCode(wrongCode($code))
        ->assertSessionHasErrors(['code' => 'Deze code is onjuist of verlopen. Vraag zo nodig een nieuwe code aan.']);

    $this->assertGuest();
    expect(LoginCode::query()->sole()->attempts)->toBe(1);
});

it('refuses malformed codes without counting an attempt', function (mixed $bad) {
    issueCode();

    verifyCode($bad)->assertSessionHasErrors('code');

    $this->assertGuest();
    expect(LoginCode::query()->sole()->attempts)->toBe(0);
})->with([
    'letters' => ['abcdef'],
    'mixed' => ['12a456'],
    'five digits' => ['12345'],
    'seven digits' => ['1234567'],
    'empty' => [''],
    'spaces inside' => ['123 456'],
    'newline inside' => ["123\n456"],
    'trailing newline injection' => ["123456\nx"],
    'unicode digits' => ["\u{0661}\u{0662}\u{0663}\u{0664}\u{0665}\u{0666}"],
    'fullwidth digits' => ['１２３４５６'],
    'array' => [['123456']],
    'null' => [null],
    'plus sign' => ['+12345'],
    'decimal' => ['12.456'],
    'very long' => [str_repeat('1', 5000)],
]);

it('refuses a request with no code', function () {
    issueCode();

    post(route('login.store'), ['email' => 'robbin@example.com'])->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('refuses a malformed or array email', function (mixed $email) {
    $code = issueCode();

    verifyCode($code, $email)->assertSessionHasErrors('email');

    $this->assertGuest();
})->with([
    'array' => [['robbin@example.com']],
    'empty' => [''],
    'newline' => ["robbin@example.com\nx"],
]);

it('kills the code on the fifth wrong attempt, even for the right code afterwards', function () {
    $code = issueCode();
    $wrong = wrongCode($code);

    foreach (range(1, 4) as $ignored) {
        verifyCode($wrong)->assertSessionHasErrors('code');
    }

    expect(LoginCode::query()->sole()->attempts)->toBe(4);

    verifyCode($wrong)->assertSessionHasErrors('code');
    expect(LoginCode::query()->count())->toBe(0);

    verifyCode($code)->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('still accepts the right code after four wrong attempts', function () {
    $code = issueCode();

    foreach (range(1, 4) as $ignored) {
        verifyCode(wrongCode($code));
    }

    verifyCode($code)->assertRedirect(route('recipes.index'));
});

it('does not let a replay of a used code log in again', function () {
    $code = issueCode();

    verifyCode($code)->assertRedirect(route('recipes.index'));
    Auth::guard()->logout();
    $this->flushSession();

    verifyCode($code)->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('refuses an expired code and removes it', function () {
    $code = issueCode();

    travel(10)->minutes();
    travel(1)->seconds();

    verifyCode($code)->assertSessionHasErrors('code');

    $this->assertGuest();
    expect(LoginCode::query()->count())->toBe(0);
});

it('still accepts the code just before it expires', function () {
    $code = issueCode();

    travel(9)->minutes();
    travel(59)->seconds();

    verifyCode($code)->assertRedirect(route('recipes.index'));
});

it('gives an unknown address the same answer as a wrong code', function () {
    $code = issueCode();

    $known = verifyCode(wrongCode($code));
    $unknown = verifyCode($code, 'stranger@example.com');

    expect($unknown->getStatusCode())->toBe($known->getStatusCode())
        ->and($unknown->headers->get('Location'))->toBe($known->headers->get('Location'));
    $unknown->assertSessionHasErrors(['code' => 'Deze code is onjuist of verlopen. Vraag zo nodig een nieuwe code aan.']);
    $this->assertGuest();
});

it('gives an address with no code the same answer as a wrong one', function () {
    verifyCode('123456')->assertSessionHasErrors(['code' => 'Deze code is onjuist of verlopen. Vraag zo nodig een nieuwe code aan.']);
});

it('binds a code to the user it was issued for', function () {
    $code = issueCode();
    $other = User::factory()->create(['email' => 'other@example.com']);

    verifyCode($code, 'other@example.com')->assertSessionHasErrors('code');
    $this->assertGuest();
    expect(LoginCode::query()->where('user_id', $this->user->id)->count())->toBe(1);

    $other->loginCode()->create([
        'code_hash' => LoginCode::query()->where('user_id', $this->user->id)->sole()->code_hash,
        'expires_at' => now()->addMinutes(10),
    ]);

    verifyCode($code, 'other@example.com')->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('stores only a keyed hash of the code, not the code', function () {
    $code = issueCode();

    $hash = LoginCode::query()->sole()->code_hash;

    expect($hash)->not->toBe($code)
        ->and($hash)->not->toContain($code)
        ->and($hash)->toBe(hash_hmac('sha256', $this->user->id.'|'.$code, (string) config('app.key')))
        ->and(DB::table('login_codes')->value('code_hash'))->not->toBe($code);
});

it('lets only one of two simultaneous requests use the same code', function () {
    $code = issueCode();
    $results = [];
    $inner = false;

    DB::listen(function ($query) use (&$inner, &$results, $code): void {
        if ($inner || ! str_starts_with($query->sql, 'update "login_codes" set "attempts"')) {
            return;
        }

        $inner = true;
        $results[] = app(VerifyLoginCode::class)->handle('robbin@example.com', $code);
    });

    $results[] = app(VerifyLoginCode::class)->handle('robbin@example.com', $code);

    expect(array_values(array_filter($results, fn (?User $user): bool => $user instanceof User)))->toHaveCount(1);
});

it('cannot be brute forced by racing guesses past the attempt cap', function () {
    $code = issueCode();
    $wrong = wrongCode($code);
    $action = app(VerifyLoginCode::class);

    foreach (range(1, 8) as $ignored) {
        $action->handle('robbin@example.com', $wrong);
    }

    expect(LoginCode::query()->count())->toBe(0)
        ->and($action->handle('robbin@example.com', $code))->toBeNull();
});

it('throttles verification attempts per address on the network', function () {
    foreach (range(1, 10) as $ignored) {
        verifyCode('123456', 'stranger@example.com');
    }

    verifyCode('123456', 'stranger@example.com')->assertStatus(429);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
        ->post(route('login.store'), ['email' => 'stranger@example.com', 'code' => '123456'])
        ->assertSessionHasErrors('code');
});

it('keeps a logged in visitor out of the verify route', function () {
    $code = issueCode();
    Auth::guard()->login(User::factory()->create());

    verifyCode($code)->assertRedirect();

    expect(LoginCode::query()->where('user_id', $this->user->id)->sole()->attempts)->toBe(0);
});

it('never logs the code or the token while verifying', function () {
    $logs = captureLogs();
    $code = issueCode();

    verifyCode(wrongCode($code));
    verifyCode($code);

    expect($logs())->not->toContain($code)->not->toContain('zob_test_token_value');
});

it('keeps a code out of the query string of every redirect', function () {
    $code = issueCode();

    $response = verifyCode($code);

    expect((string) $response->headers->get('Location'))->not->toContain('code')->not->toContain($code);
});

it('does not touch the password column when logging in', function () {
    $before = $this->user->password;
    $code = issueCode();

    verifyCode($code);

    expect($this->user->refresh()->password)->toBe($before)
        ->and(Hash::check($code, (string) $this->user->password))->toBeFalse();
});

it('refuses a code row that is already out of attempts', function () {
    $this->user->loginCode()->create([
        'code_hash' => LoginCode::hashFor($this->user->id, '123456'),
        'expires_at' => now()->addMinutes(10),
    ]);
    LoginCode::query()->update(['attempts' => LoginCode::MAX_ATTEMPTS]);

    expect(app(VerifyLoginCode::class)->handle('robbin@example.com', '123456'))->toBeNull();
});
