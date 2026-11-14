<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tag;
use Illuminate\Support\Str;

final readonly class RecipeFilters
{
    public ?string $search;

    public ?string $country;

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
        ?string $country = null,
        public int $page = 1,
    ) {
        $search = $search === null ? '' : Str::trim($search);
        $this->search = $search === '' ? null : $search;

        $country = $country === null ? '' : Str::trim($country);
        $this->country = $country === '' ? null : $country;

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
        return $this->search !== null || $this->tags !== [] || $this->country !== null || $this->maxMinutes !== null || $this->houdbaar;
    }

    /**
     * @return array{q: string|null, tag: list<string>, max_minutes: int|null, houdbaar: bool, country: string|null, active: bool}
     */
    public function toArray(): array
    {
        return [
            'q' => $this->search,
            'tag' => $this->tags,
            'max_minutes' => $this->maxMinutes,
            'houdbaar' => $this->houdbaar,
            'country' => $this->country,
            'active' => $this->isActive(),
        ];
    }
}
