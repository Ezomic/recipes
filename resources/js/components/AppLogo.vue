<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChefHat } from '@lucide/vue';

const name = usePage().props.name;
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <ChefHat class="size-5" aria-hidden="true" />
    </div>
    <div class="ml-1 grid flex-1 text-left">
        <span class="truncate font-serif text-lg leading-tight font-semibold">{{
            name
        }}</span>
    </div>
</template>
