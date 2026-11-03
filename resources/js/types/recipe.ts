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
