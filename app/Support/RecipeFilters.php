<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tag;
use Illuminate\Support\Str;

final readonly class RecipeFilters
{
    public ?string $search;

    /** @var list<string> */
    public array $tags;

    /**
     * @param  array<array-key, mixed>  $tags
     */
    public function __construct(
        ?string $search,
        array $tags,
        public ?int $maxMinutes,
        public bool $houdbaar,
        public int $page = 1,
    ) {
        $search = $search === null ? '' : Str::trim($search);
        $this->search = $search === '' ? null : $search;

        $names = [];

        foreach ($tags as $tag) {
            $name = is_string($tag) ? Tag::normalise($tag) : '';

            if ($name !== '') {
                $names[] = $name;
            }
        }

        $this->tags = array_values(array_unique($names));
    }

    public function isActive(): bool
    {
        return $this->search !== null || $this->tags !== [] || $this->maxMinutes !== null || $this->houdbaar;
    }

    /**
     * @return array{q: string|null, tag: list<string>, max_minutes: int|null, houdbaar: bool, active: bool}
     */
    public function toArray(): array
    {
        return [
            'q' => $this->search,
            'tag' => $this->tags,
            'max_minutes' => $this->maxMinutes,
            'houdbaar' => $this->houdbaar,
            'active' => $this->isActive(),
        ];
    }
}
