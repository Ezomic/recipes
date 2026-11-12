<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'E-mailadres bevestigen',
        description:
            'Bevestig je e-mailadres met de link die we je zojuist hebben gemaild.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="E-mailadres bevestigen" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        Er is een nieuwe bevestigingslink gestuurd naar het e-mailadres dat je
        hebt opgegeven.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Bevestigingsmail opnieuw versturen
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Uitloggen
        </TextLink>
    </Form>
</template>
