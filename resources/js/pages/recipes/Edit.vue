<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RecipeForm from '@/components/RecipeForm.vue';
import { index } from '@/routes/recipes';
import type { RecipeWithIngredients } from '@/types';

defineProps<{
    recipe: RecipeWithIngredients;
    knownTags: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Recepten', href: index() }],
    },
});
</script>

<template>
    <Head title="Recept bewerken" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Recept bewerken</h1>

        <RecipeForm :recipe="recipe" :known-tags="knownTags" />
    </div>
</template>
