<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Weergave-instellingen',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Weergave-instellingen" />

    <h1 class="sr-only">Weergave-instellingen</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Weergave-instellingen"
            description="Pas de weergave van je account aan"
        />
        <AppearanceTabs />
    </div>
</template>
