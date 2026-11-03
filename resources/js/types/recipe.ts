export const INGREDIENT_UNITS = [
    'g',
    'kg',
    'ml',
    'l',
    'stuks',
    'eetlepel',
    'theelepel',
] as const;

export type IngredientUnit = (typeof INGREDIENT_UNITS)[number];

export type Ingredient = {
    id: number;
    position: number;
    quantity: number | null;
    unit: IngredientUnit | null;
    name: string;
};

export type Recipe = {
    id: number;
    title: string;
    steps: string[];
    preparation_minutes: number;
    servings: number;
    houdbaar: boolean;
    created_at: string;
    updated_at: string;
};

export type RecipeWithIngredients = Recipe & {
    ingredients: Ingredient[];
};
