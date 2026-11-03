<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RecipeForm from '@/components/RecipeForm.vue';
import { create, index } from '@/routes/recipes';

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

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Nieuw recept</h1>

        <RecipeForm />
    </div>
</template>
