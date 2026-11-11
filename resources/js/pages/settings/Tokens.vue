<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Check, Copy, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy, index, store } from '@/routes/api-tokens';
import type { ApiToken } from '@/types';

defineProps<{
    tokens: ApiToken[];
    createdToken: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'API-tokens', href: index() }],
    },
});

const copied = ref(false);

async function copy(token: string): Promise<void> {
    await navigator.clipboard.writeText(token);
    copied.value = true;

    window.setTimeout(() => {
        copied.value = false;
    }, 2000);
}

function revoke(id: number): void {
    router.delete(destroy(id).url, { preserveScroll: true });
}
</script>

<template>
    <Head title="API-tokens" />

    <h1 class="sr-only">API-tokens</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="API-tokens"
            description="Met een token log je in bij de API. Behandel het als een wachtwoord."
        />

        <!-- Rendered from a session flash, so it is here for exactly one render.
             Only a hash is stored, so this is the only chance to copy it. -->
        <Alert v-if="createdToken">
            <AlertTitle>Kopieer je token nu</AlertTitle>
            <AlertDescription class="space-y-3">
                <p>Dit is de enige keer dat het getoond wordt.</p>
                <div class="flex w-full items-center gap-2">
                    <code
                        class="min-w-0 flex-1 truncate rounded-md bg-muted px-3 py-2 font-mono text-xs"
                        data-testid="created-token"
                    >
                        {{ createdToken }}
                    </code>
                    <Button
                        size="sm"
                        variant="outline"
                        :aria-label="copied ? 'Gekopieerd' : 'Kopieer token'"
                        @click="copy(createdToken)"
                    >
                        <Check v-if="copied" class="size-4" />
                        <Copy v-else class="size-4" />
                        {{ copied ? 'Gekopieerd' : 'Kopieer' }}
                    </Button>
                </div>
            </AlertDescription>
        </Alert>

        <Form
            v-bind="store.form()"
            reset-on-success
            class="flex items-end gap-2"
            #default="{ errors, processing }"
        >
            <div class="grid flex-1 gap-2">
                <Label for="name">Naam van het token</Label>
                <Input
                    id="name"
                    name="name"
                    placeholder="bijv. Laptop CLI"
                    required
                />
                <InputError :message="errors.name" />
            </div>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                Token aanmaken
            </Button>
        </Form>

        <div class="overflow-hidden rounded-lg border border-border">
            <p
                v-if="tokens.length === 0"
                class="p-6 text-center text-sm text-muted-foreground"
            >
                Nog geen tokens.
            </p>

            <div
                v-for="token in tokens"
                v-else
                :key="token.id"
                class="flex items-center justify-between gap-4 border-b border-border p-4 last:border-b-0"
            >
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ token.name }}</p>
                    <p class="text-sm text-muted-foreground">
                        Aangemaakt {{ token.created_at_diff }} &middot;
                        {{
                            token.last_used_at_diff
                                ? `laatst gebruikt ${token.last_used_at_diff}`
                                : 'nooit gebruikt'
                        }}
                        <template v-if="token.expires_at_diff">
                            &middot;
                            <span
                                :class="{ 'text-destructive': token.expired }"
                            >
                                {{ token.expired ? 'verlopen' : 'verloopt' }}
                                {{ token.expires_at_diff }}
                            </span>
                        </template>
                    </p>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    :aria-label="`${token.name} intrekken`"
                    @click="revoke(token.id)"
                >
                    <Trash2 class="size-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
