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

export type Tag = {
    id: number;
    name: string;
};

export type RecipeFilters = {
    q: string | null;
    tag: string[];
    max_minutes: number | null;
    houdbaar: boolean;
    active: boolean;
};

export type Recipe = {
    id: number;
    title: string;
    steps: string[];
    preparation_minutes: number;
    servings: number;
    houdbaar: boolean;
    photo_version: string | null;
    created_at: string;
    updated_at: string;
    tags: Tag[];
};

export type RecipeWithIngredients = Recipe & {
    ingredients: Ingredient[];
};
