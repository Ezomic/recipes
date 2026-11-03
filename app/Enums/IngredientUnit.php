<?php

declare(strict_types=1);

namespace App\Enums;

enum IngredientUnit: string
{
    case Gram = 'g';
    case Kilogram = 'kg';
    case Milliliter = 'ml';
    case Liter = 'l';
    case Piece = 'stuks';
    case Tablespoon = 'eetlepel';
    case Teaspoon = 'theelepel';
}
