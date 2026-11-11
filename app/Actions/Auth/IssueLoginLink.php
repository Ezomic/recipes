<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\LoginLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class IssueLoginLink
{
    public const VALID_MINUTES = 15;

    public function handle(User $user): string
    {
        $token = Str::random(64);
        $expiresAt = now()->addMinutes(self::VALID_MINUTES);

        DB::transaction(function () use ($user, $token, $expiresAt): void {
            LoginLink::query()->where('user_id', $user->id)->delete();

            $link = new LoginLink;
            $link->forceFill([
                'user_id' => $user->id,
                'token_hash' => LoginLink::hashToken($token),
                'expires_at' => $expiresAt,
            ])->save();
        });

        return URL::temporarySignedRoute('login.link', $expiresAt, ['token' => $token]);
    }
}
