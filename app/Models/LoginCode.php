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
 * @property string $code_hash
 * @property int $attempts
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['code_hash', 'expires_at'])]
#[Hidden(['code_hash'])]
class LoginCode extends Model
{
    public const MAX_ATTEMPTS = 5;

    public const VALID_MINUTES = 10;

    public static function hashFor(int $userId, string $code): string
    {
        return hash_hmac('sha256', $userId.'|'.$code, config()->string('app.key'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }
}
