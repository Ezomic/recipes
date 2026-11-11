<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RecipeForm from '@/components/RecipeForm.vue';
import { create, index } from '@/routes/recipes';

defineProps<{
    knownTags: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Recepten', href: index() },
            { title: 'Nieuw recept', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Nieuw recept" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-8">
        <h1 class="text-3xl font-semibold md:text-4xl">Nieuw recept</h1>

        <RecipeForm :known-tags="knownTags" />
    </div>
</template>
