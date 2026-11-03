<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\IngredientUnit;
use App\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

trait RecipeValidationRules
{
    /**
     * @return array<string, array<int, Enum|ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'steps' => ['nullable', 'array', 'max:100'],
            'steps.*' => ['nullable', 'string', 'max:2000'],
            'preparation_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'servings' => ['required', 'integer', 'min:1', 'max:999'],
            'houdbaar' => ['required', 'boolean'],
            'ingredients' => ['nullable', 'array', 'max:100'],
            'ingredients.*' => ['array'],
            'ingredients.*.quantity' => ['nullable', 'required_with:ingredients.*.unit', 'numeric', 'decimal:0,2', 'gt:0', 'max:100000'],
            'ingredients.*.unit' => ['nullable', Rule::enum(IngredientUnit::class)],
            'ingredients.*.name' => ['nullable', 'required_with:ingredients.*.quantity,ingredients.*.unit', 'string', 'max:255'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:'.Tag::MAX_LENGTH],
            'photo' => ['nullable', 'bail', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp', 'dimensions:max_width=6000,max_height=6000'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    public function photo(): ?UploadedFile
    {
        $photo = $this->file('photo');

        return $photo instanceof UploadedFile ? $photo : null;
    }

    protected function prepareForValidation(): void
    {
        $ingredients = $this->input('ingredients');
        $tags = $this->input('tags');

        $this->merge([
            ...(is_array($ingredients) ? ['ingredients' => array_map($this->normaliseIngredient(...), $ingredients)] : []),
            ...(is_array($tags) ? ['tags' => $this->normaliseTags($tags)] : []),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $tags
     * @return list<mixed>
     */
    private function normaliseTags(array $tags): array
    {
        $kept = [];

        foreach ($tags as $tag) {
            if (is_string($tag)) {
                $tag = Tag::normalise($tag);
            }

            if ($tag !== null && $tag !== '') {
                $kept[] = $tag;
            }
        }

        return array_values(array_unique($kept, SORT_REGULAR));
    }

    private function normaliseIngredient(mixed $row): mixed
    {
        if (! is_array($row)) {
            return $row;
        }

        if (is_string($row['name'] ?? null)) {
            $name = Str::trim($row['name']);
            $row['name'] = $name === '' ? null : $name;
        }

        if (is_string($row['quantity'] ?? null)) {
            $quantity = Str::trim($row['quantity']);
            $row['quantity'] = preg_match('/^\d*,\d+$/', $quantity) === 1 ? str_replace(',', '.', $quantity) : ($quantity === '' ? null : $quantity);
        }

        return $row;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Vul een titel in.',
            'title.string' => 'De titel moet tekst zijn.',
            'title.max' => 'De titel mag maximaal 255 tekens lang zijn.',
            'steps.array' => 'De bereidingsstappen zijn ongeldig.',
            'steps.max' => 'Een recept mag maximaal 100 stappen hebben.',
            'steps.*.string' => 'Een stap moet tekst zijn.',
            'steps.*.max' => 'Een stap mag maximaal 2000 tekens lang zijn.',
            'preparation_minutes.required' => 'Vul de bereidingstijd in.',
            'preparation_minutes.integer' => 'De bereidingstijd moet een geheel aantal minuten zijn.',
            'preparation_minutes.min' => 'De bereidingstijd moet minimaal 1 minuut zijn.',
            'preparation_minutes.max' => 'De bereidingstijd mag maximaal 10080 minuten (een week) zijn.',
            'servings.required' => 'Vul het aantal porties in.',
            'servings.integer' => 'Het aantal porties moet een geheel getal zijn.',
            'servings.min' => 'Het aantal porties moet minimaal 1 zijn.',
            'servings.max' => 'Het aantal porties mag maximaal 999 zijn.',
            'houdbaar.required' => 'Geef aan of het recept houdbaar is.',
            'houdbaar.boolean' => 'Houdbaar moet ja of nee zijn.',
            'ingredients.array' => 'De ingrediënten zijn ongeldig.',
            'ingredients.max' => 'Een recept mag maximaal 100 ingrediënten hebben.',
            'ingredients.*.array' => 'Ingrediënt :position is ongeldig.',
            'ingredients.*.quantity.required_with' => 'Vul bij ingrediënt :position een hoeveelheid in of kies geen eenheid.',
            'ingredients.*.quantity.numeric' => 'De hoeveelheid van ingrediënt :position moet een getal zijn.',
            'ingredients.*.quantity.decimal' => 'De hoeveelheid van ingrediënt :position mag maximaal 2 decimalen hebben.',
            'ingredients.*.quantity.gt' => 'De hoeveelheid van ingrediënt :position moet groter zijn dan 0.',
            'ingredients.*.quantity.max' => 'De hoeveelheid van ingrediënt :position mag maximaal 100000 zijn.',
            'ingredients.*.unit' => 'Kies bij ingrediënt :position een eenheid uit de lijst.',
            'ingredients.*.name.required_with' => 'Vul bij ingrediënt :position een naam in.',
            'ingredients.*.name.string' => 'De naam van ingrediënt :position moet tekst zijn.',
            'ingredients.*.name.max' => 'De naam van ingrediënt :position mag maximaal 255 tekens lang zijn.',
            'tags.array' => 'De tags zijn ongeldig.',
            'tags.max' => 'Een recept mag maximaal 20 tags hebben.',
            'tags.*.string' => 'Een tag moet tekst zijn.',
            'tags.*.max' => 'Een tag mag maximaal 50 tekens lang zijn.',
            'photo.file' => 'Kies een afbeelding om te uploaden.',
            'photo.uploaded' => 'Het uploaden van de foto is mislukt.',
            'photo.max' => 'De foto mag maximaal 5 MB groot zijn.',
            'photo.mimetypes' => 'De foto moet een jpeg-, png- of webp-afbeelding zijn.',
            'photo.dimensions' => 'De foto mag maximaal 6000 bij 6000 pixels groot zijn.',
            'remove_photo.boolean' => 'Foto verwijderen moet ja of nee zijn.',
        ];
    }
}
