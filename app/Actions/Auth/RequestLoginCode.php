<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\LoginCode;
use App\Models\User;
use App\Services\ZeroMailer;
use App\Services\ZeroMailFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class RequestLoginCode
{
    private const PER_ADDRESS_HOURLY = 5;

    private const PER_NETWORK_HOURLY = 10;

    public function __construct(private readonly ZeroMailer $mailer) {}

    /**
     * Nothing here depends on whether the address belongs to a user, except the mail itself.
     *
     * @throws ValidationException
     */
    public function handle(string $email, string $ip): void
    {
        $this->throttle($email, $ip);

        $user = User::query()->where('email', $email)->first();
        $code = sprintf('%06d', random_int(0, 999_999));

        if (! $user instanceof User) {
            LoginCode::hashFor(0, $code);

            return;
        }

        $this->store($user, $code);

        try {
            $this->mailer->send(
                $user->email,
                'Je inlogcode voor '.config()->string('app.name'),
                $this->body($code),
            );
        } catch (ZeroMailFailed) {
            LoginCode::query()->where('user_id', $user->id)->delete();

            throw ValidationException::withMessages([
                'email' => 'De code kon niet worden verstuurd, probeer het later opnieuw.',
            ]);
        }
    }

    private function store(User $user, string $code): void
    {
        DB::transaction(function () use ($user, $code): void {
            LoginCode::query()->where('user_id', $user->id)->delete();

            $user->loginCode()->create([
                'code_hash' => LoginCode::hashFor($user->id, $code),
                'expires_at' => now()->addMinutes(LoginCode::VALID_MINUTES),
            ]);
        });
    }

    private function body(string $code): string
    {
        $minutes = LoginCode::VALID_MINUTES;

        return "Je inlogcode is: {$code}\n\n"
            ."De code is {$minutes} minuten geldig en werkt één keer.\n\n"
            ."Heb je deze code niet aangevraagd? Dan kun je deze e-mail negeren.\n";
    }

    /**
     * @throws ValidationException
     */
    private function throttle(string $email, string $ip): void
    {
        $addressKey = 'login-code:address:'.hash('sha256', $email);
        $minuteKey = $addressKey.':minute';
        $hourKey = $addressKey.':hour';
        $networkKey = 'login-code:network:'.$ip;

        if (RateLimiter::tooManyAttempts($minuteKey, 1)) {
            $seconds = RateLimiter::availableIn($minuteKey);

            throw ValidationException::withMessages([
                'email' => "Wacht nog {$seconds} ".($seconds === 1 ? 'seconde' : 'seconden').' voordat je een nieuwe code aanvraagt.',
            ]);
        }

        if (RateLimiter::tooManyAttempts($hourKey, self::PER_ADDRESS_HOURLY)
            || RateLimiter::tooManyAttempts($networkKey, self::PER_NETWORK_HOURLY)) {
            throw ValidationException::withMessages([
                'email' => 'Er zijn te veel codes aangevraagd. Probeer het later opnieuw.',
            ]);
        }

        RateLimiter::hit($minuteKey, 60);
        RateLimiter::hit($hourKey, 3600);
        RateLimiter::hit($networkKey, 3600);
    }
}
