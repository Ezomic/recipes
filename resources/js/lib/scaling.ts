import type { IngredientUnit } from '@/types';

type Unit = IngredientUnit | null;

const FRACTIONS: Record<number, string> = { 0.25: '¼', 0.5: '½', 0.75: '¾' };

function roundToDecimals(value: number, decimals: number): number {
    const factor = 10 ** decimals;

    return Math.round(value * factor) / factor;
}

function roundToStep(value: number, step: number): number {
    return Math.max(Math.round(value / step) * step, step);
}

function round(value: number, unit: Unit): number {
    switch (unit) {
        case 'g':
        case 'ml':
            return value >= 10
                ? Math.round(value)
                : Math.max(roundToDecimals(value, 1), 0.1);
        case 'kg':
        case 'l':
            return Math.max(roundToDecimals(value, 2), 0.01);
        case 'eetlepel':
        case 'theelepel':
            return roundToStep(value, 0.25);
        default:
            return roundToStep(value, 0.5);
    }
}

export function scaleQuantity(
    quantity: number,
    unit: Unit,
    recipeServings: number,
    wantedServings: number,
): number {
    if (wantedServings === recipeServings) {
        return quantity;
    }

    return round((quantity * wantedServings) / recipeServings, unit);
}

export function formatQuantity(value: number, unit: Unit): string {
    const usesFractions =
        unit === null ||
        unit === 'stuks' ||
        unit === 'eetlepel' ||
        unit === 'theelepel';
    const whole = Math.floor(value);
    const fraction = FRACTIONS[value - whole];

    if (usesFractions && fraction !== undefined) {
        return `${whole === 0 ? '' : whole}${fraction}`;
    }

    return String(roundToDecimals(value, 2)).replace('.', ',');
}
