<?php

declare(strict_types=1);

use App\Actions\Auth\RedeemLoginLink;
use App\Models\LoginLink;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\get;
use function Pest\Laravel\travel;

function issueLoginLink(): string
{
    Artisan::call('user:login-link');

    preg_match('#https?://\S+#', Artisan::output(), $matches);

    return $matches[0] ?? throw new LogicException('No link printed.');
}

it('refuses when there is no user', function () {
    $this->artisan('user:login-link')->expectsOutputToContain('No user')->assertFailed();

    expect(LoginLink::query()->count())->toBe(0);
});

it('prints a signed link that logs the one user in', function () {
    $user = User::factory()->create();
    Http::fake();

    $url = issueLoginLink();

    get($url)->assertRedirect(route('recipes.index'));

    $this->assertAuthenticatedAs($user);
    Http::assertNothingSent();
});

it('regenerates the session when the link is used', function () {
    User::factory()->create();
    $url = issueLoginLink();
    $before = session()->getId();

    get($url);

    expect(session()->getId())->not->toBe($before);
});

it('works only once', function () {
    User::factory()->create();
    $url = issueLoginLink();

    get($url)->assertRedirect();
    Auth::guard()->logout();
    $this->flushSession();

    get($url)->assertForbidden();
    $this->assertGuest();
});

it('expires after fifteen minutes', function () {
    User::factory()->create();
    $url = issueLoginLink();

    travel(15)->minutes();
    travel(1)->seconds();

    get($url)->assertForbidden();
    $this->assertGuest();
});

it('works just before it expires', function () {
    User::factory()->create();
    $url = issueLoginLink();

    travel(14)->minutes();

    get($url)->assertRedirect(route('recipes.index'));
});

it('is dead once a newer link was issued', function () {
    User::factory()->create();
    $first = issueLoginLink();
    $second = issueLoginLink();

    get($first)->assertForbidden();
    $this->assertGuest();

    get($second)->assertRedirect(route('recipes.index'));
});

it('refuses a tampered signature, token or expiry', function (callable $tamper) {
    User::factory()->create();
    $url = issueLoginLink();

    get($tamper($url))->assertForbidden();
    $this->assertGuest();
})->with([
    'signature' => [fn (string $url): string => preg_replace('/signature=(.)/', 'signature=x', $url) ?? $url],
    'token' => [fn (string $url): string => preg_replace('#login/link/(.)#', 'login/link/Z', $url) ?? $url],
    'expiry' => [fn (string $url): string => preg_replace('/expires=\d+/', 'expires=99999999999', $url) ?? $url],
    'no signature' => [fn (string $url): string => (string) strtok($url, '?')],
]);

it('refuses a correctly signed link for a token that was never issued', function () {
    User::factory()->create();

    get(URL::temporarySignedRoute('login.link', now()->addMinutes(15), ['token' => str_repeat('a', 64)]))
        ->assertForbidden();
    $this->assertGuest();
});

it('stores only a hash of the token', function () {
    User::factory()->create();
    $url = issueLoginLink();

    $token = basename((string) parse_url($url, PHP_URL_PATH));

    expect(LoginLink::query()->sole()->token_hash)->not->toContain($token);
});

it('does not log the token', function () {
    User::factory()->create();
    $logs = captureLogs();

    $url = issueLoginLink();
    get($url);

    expect($logs())->not->toContain(basename((string) parse_url($url, PHP_URL_PATH)));
});

it('refuses an expired token even if the signature were still valid', function () {
    User::factory()->create();
    $url = issueLoginLink();
    $token = basename((string) parse_url($url, PHP_URL_PATH));

    travel(16)->minutes();

    expect(app(RedeemLoginLink::class)->handle($token))->toBeNull()
        ->and(LoginLink::query()->count())->toBe(0);
});
