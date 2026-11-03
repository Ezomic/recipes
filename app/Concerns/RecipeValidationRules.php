<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait RecipeValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
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
        ];
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
        ];
    }
}
