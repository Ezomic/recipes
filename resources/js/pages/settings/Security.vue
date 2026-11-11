<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import { edit } from '@/routes/security';

defineProps<ManagePasskeysProps>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Beveiligingsinstellingen',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Beveiligingsinstellingen" />

    <h1 class="sr-only">Beveiligingsinstellingen</h1>

    <ManagePasskeys
        :canManagePasskeys="canManagePasskeys"
        :passkeys="passkeys"
    />
</template>
