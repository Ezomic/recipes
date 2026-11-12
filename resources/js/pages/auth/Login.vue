<script setup lang="ts">
import { Form, Head, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed, ref, watch, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { send } from '@/routes/login/code';
import { redirect as ssoRedirect } from '@/routes/sso';

defineOptions({
    layout: {
        title: 'Log in op je account',
    },
});

const props = defineProps<{
    email: string | null;
}>();

const page = usePage();
const workflowEnabled = computed<boolean>(
    () => page.props.workflow?.enabled ?? false,
);

const askingForCode = ref<boolean>(props.email !== null);

watch(
    () => props.email,
    (email) => {
        askingForCode.value = email !== null;
    },
);

watchEffect(() => {
    setLayoutProps({
        description: workflowEnabled.value
            ? 'Log in met je Thijssensoftware-account'
            : askingForCode.value
              ? 'Vul de code in die we naar je gemaild hebben'
              : 'Vul je e-mailadres in, dan sturen we je een inlogcode',
    });
});

const code = ref('');

watch(code, (value) => {
    const digits = value.replace(/\D/g, '').slice(0, 6);

    if (digits !== value) {
        code.value = digits;
    }
});
</script>

<template>
    <Head title="Inloggen" />

    <div v-if="workflowEnabled" class="flex flex-col gap-6">
        <Button class="w-full" as-child>
            <a :href="ssoRedirect().url">Inloggen met Thijssensoftware</a>
        </Button>
    </div>

    <div v-else class="flex flex-col gap-6">
        <PasskeyVerify
            label="Inloggen met een passkey"
            loading-label="Bezig met inloggen..."
            separator="Of ga verder met e-mail"
        />

        <template v-if="askingForCode && email !== null">
            <p class="text-center text-sm text-muted-foreground">
                Als
                <span class="font-medium text-foreground">{{ email }}</span>
                bij ons bekend is, hebben we daar een code naartoe gestuurd. De
                code is 10 minuten geldig.
            </p>

            <Form
                v-bind="store.form()"
                :reset-on-success="['code']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
            >
                <input type="hidden" name="email" :value="email" />

                <div class="grid gap-2">
                    <Label for="code">Code</Label>
                    <Input
                        id="code"
                        v-model="code"
                        name="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        required
                        autofocus
                        placeholder="123456"
                        class="text-center text-lg tracking-[0.5em]"
                    />
                    <InputError :message="errors.code" />
                    <InputError :message="errors.email" />
                </div>

                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" />
                    <span>Onthoud mij</span>
                </Label>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Inloggen
                </Button>
            </Form>

            <Form
                v-bind="send.form()"
                v-slot="{ errors, processing }"
                class="flex flex-col items-center gap-2"
            >
                <input type="hidden" name="email" :value="email" />
                <Button
                    type="submit"
                    variant="ghost"
                    size="sm"
                    :disabled="processing"
                    data-test="resend-code"
                >
                    <Spinner v-if="processing" />
                    Stuur een nieuwe code
                </Button>
                <InputError :message="errors.email" />
            </Form>

            <Button
                type="button"
                variant="link"
                size="sm"
                data-test="change-email"
                @click="askingForCode = false"
            >
                Ander e-mailadres gebruiken
            </Button>
        </template>

        <Form
            v-else
            v-bind="send.form()"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-2">
                <Label for="email">E-mailadres</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="naam@voorbeeld.nl"
                />
                <InputError :message="errors.email" />
            </div>

            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-test="send-code-button"
            >
                <Spinner v-if="processing" />
                Stuur me een code
            </Button>
        </Form>
    </div>
</template>
