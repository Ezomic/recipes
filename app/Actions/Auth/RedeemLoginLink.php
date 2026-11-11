<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\LoginLink;
use App\Models\User;

final class RedeemLoginLink
{
    public function handle(string $token): ?User
    {
        $link = LoginLink::query()->where('token_hash', LoginLink::hashToken($token))->first();

        if (! $link instanceof LoginLink) {
            return null;
        }

        $spent = LoginLink::query()->whereKey($link->id)->delete();

        if ($spent !== 1 || $link->expires_at->isPast()) {
            return null;
        }

        return User::query()->find($link->user_id);
    }
}
