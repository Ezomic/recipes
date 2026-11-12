<script setup lang="ts">
import { ChefHat } from '@lucide/vue';
import RecipePhoto from '@/components/RecipePhoto.vue';

defineProps<{
    recipe: { id: number; title: string; photo_version: string | null };
}>();
</script>

<template>
    <div class="relative overflow-hidden bg-secondary">
        <RecipePhoto
            v-if="recipe.photo_version !== null"
            :recipe="recipe"
            class="size-full object-cover"
        />
        <div
            v-else
            data-testid="recipe-cover-placeholder"
            class="flex size-full items-center justify-center bg-gradient-to-br from-accent to-secondary text-primary/50"
        >
            <ChefHat class="size-1/4 min-h-6 min-w-6" aria-hidden="true" />
        </div>
    </div>
</template>
