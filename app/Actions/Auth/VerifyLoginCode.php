<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\LoginCode;
use App\Models\User;

final class VerifyLoginCode
{
    /**
     * Wrong, expired, used and unknown all come back as null, so a caller cannot tell them apart.
     *
     * The attempt is counted with one conditional UPDATE before the code is compared, so racing
     * guesses cannot get past the cap, and the code is spent with a DELETE that only one of two
     * simultaneous correct submissions can win.
     */
    public function handle(string $email, string $code): ?User
    {
        $user = User::query()->where('email', $email)->first();
        $record = $user instanceof User ? $user->loginCode : null;

        if (! $user instanceof User || ! $record instanceof LoginCode) {
            hash_equals(LoginCode::hashFor(0, '000000'), LoginCode::hashFor(0, $code));

            return null;
        }

        if ($record->expires_at->isPast()) {
            $record->newQuery()->whereKey($record->id)->delete();

            return null;
        }

        $counted = $record->newQuery()
            ->whereKey($record->id)
            ->where('attempts', '<', LoginCode::MAX_ATTEMPTS)
            ->increment('attempts');

        if ($counted === 0) {
            return null;
        }

        if (! hash_equals($record->code_hash, LoginCode::hashFor($user->id, $code))) {
            $record->newQuery()
                ->whereKey($record->id)
                ->where('attempts', '>=', LoginCode::MAX_ATTEMPTS)
                ->delete();

            return null;
        }

        $spent = $record->newQuery()->whereKey($record->id)->delete();

        return $spent === 1 ? $user : null;
    }
}
