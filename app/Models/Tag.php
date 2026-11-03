<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name'])]
#[Hidden(['user_id', 'pivot', 'created_at', 'updated_at'])]
class Tag extends Model
{
    public const MAX_LENGTH = 50;

    /** @use HasFactory<TagFactory> */
    use HasFactory;

    public static function normalise(string $name): string
    {
        $name = preg_replace('/[\x{200B}-\x{200F}\x{00AD}\x{180E}\x{2060}\x{FEFF}]/u', '', mb_scrub($name));
        $name = preg_replace('/[\s\p{Z}]+/u', ' ', (string) $name);

        return mb_strtolower(Str::trim((string) $name));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Recipe, $this>
     */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class);
    }
}
