<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { index } from '@/routes/recipes';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Recepten', href: index() }],
    },
});
</script>

<template>
    <Head title="Recepten" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Recepten</h1>

        <p
            class="rounded-xl border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground"
        >
            Nog geen recepten
        </p>
    </div>
</template>
