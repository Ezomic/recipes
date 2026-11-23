<?php

declare(strict_types=1);

namespace App\Http\Requests\Recipes;

use App\Models\Tag;
use App\Support\RecipeFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator as ValidationValidator;

final class IndexRecipesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'array', 'max:10'],
            'tag.*' => ['nullable', 'string', 'max:'.Tag::MAX_LENGTH],
            'country' => ['nullable', 'string', 'max:60'],
            'max_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'houdbaar' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function validationData(): array
    {
        return $this->query();
    }

    public function filters(): RecipeFilters
    {
        /** @var ValidationValidator $validator */
        $validator = $this->getValidatorInstance();
        $valid = $validator->valid();
        $search = $valid['q'] ?? null;
        $tags = $valid['tag'] ?? [];
        $country = $valid['country'] ?? null;
        $maxMinutes = $valid['max_minutes'] ?? null;
        $page = $valid['page'] ?? null;

        return new RecipeFilters(
            search: is_string($search) ? $search : null,
            tags: is_array($tags) ? $tags : [],
            maxMinutes: is_numeric($maxMinutes) ? (int) $maxMinutes : null,
            houdbaar: filter_var($valid['houdbaar'] ?? false, FILTER_VALIDATE_BOOLEAN),
            country: is_string($country) ? $country : null,
            page: is_numeric($page) ? (int) $page : 1,
        );
    }

    // A filter that fails its rule is dropped, not answered with a 422, so a stale or hand-edited URL still shows the list.
    protected function failedValidation(Validator $validator): void {}
}
