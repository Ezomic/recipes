<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['token_hash', 'expires_at'])]
#[Hidden(['token_hash'])]
class LoginLink extends Model
{
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }
}
