<script setup lang="ts">
import { computed } from 'vue';
import { photo } from '@/routes/recipes';

const props = defineProps<{
    recipe: { id: number; title: string; photo_version: string | null };
}>();

const src = computed<string | null>(() =>
    props.recipe.photo_version === null
        ? null
        : photo(props.recipe.id, { query: { v: props.recipe.photo_version } })
              .url,
);
</script>

<template>
    <img
        v-if="src"
        :src="src"
        :alt="`Foto van ${recipe.title}`"
        loading="lazy"
        decoding="async"
    />
</template>
