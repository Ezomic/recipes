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

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-8">
        <h1 class="text-3xl font-semibold md:text-4xl">Recept bewerken</h1>

        <RecipeForm :recipe="recipe" :known-tags="knownTags" />
    </div>
</template>
