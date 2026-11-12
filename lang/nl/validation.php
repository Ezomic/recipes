<?php

declare(strict_types=1);

return [
    'accepted' => ':Attribute moet worden geaccepteerd.',
    'array' => ':Attribute moet een lijst zijn.',
    'boolean' => ':Attribute moet ja of nee zijn.',
    'decimal' => ':Attribute mag maximaal :decimal decimalen hebben.',
    'email' => ':Attribute moet een geldig e-mailadres zijn.',
    'enum' => 'De gekozen waarde voor :attribute is ongeldig.',
    'file' => ':Attribute moet een bestand zijn.',
    'gt' => [
        'numeric' => ':Attribute moet groter zijn dan :value.',
    ],
    'image' => ':Attribute moet een afbeelding zijn.',
    'in' => 'De gekozen waarde voor :attribute is ongeldig.',
    'integer' => ':Attribute moet een geheel getal zijn.',
    'max' => [
        'array' => ':Attribute mag maximaal :max items bevatten.',
        'file' => ':Attribute mag niet groter zijn dan :max kilobytes.',
        'numeric' => ':Attribute mag niet groter zijn dan :max.',
        'string' => ':Attribute mag niet langer zijn dan :max tekens.',
    ],
    'mimes' => ':Attribute moet een bestand van het type :values zijn.',
    'min' => [
        'array' => ':Attribute moet minimaal :min items bevatten.',
        'file' => ':Attribute moet minimaal :min kilobytes groot zijn.',
        'numeric' => ':Attribute moet minimaal :min zijn.',
        'string' => ':Attribute moet minimaal :min tekens lang zijn.',
    ],
    'numeric' => ':Attribute moet een getal zijn.',
    'regex' => 'Het formaat van :attribute is ongeldig.',
    'required' => ':Attribute is verplicht.',
    'required_with' => ':Attribute is verplicht als :values is ingevuld.',
    'string' => ':Attribute moet tekst zijn.',
    'unique' => ':Attribute is al in gebruik.',
    'uploaded' => 'Het uploaden van :attribute is mislukt.',
    'custom' => [],
    'attributes' => [
        'code' => 'code',
        'email' => 'e-mailadres',
        'name' => 'naam',
        'title' => 'titel',
        'body' => 'tekst',
    ],
];
