<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { index as recipesIndex } from '@/routes/recipes';

const props = defineProps<{
    status: number;
}>();

type ErrorCopy = {
    title: string;
    description: string;
};

const copy: Record<number, ErrorCopy> = {
    403: {
        title: 'Geen toegang',
        description: 'Je hebt geen toestemming om deze pagina te bekijken.',
    },
    404: {
        title: 'Pagina niet gevonden',
        description: 'Deze pagina bestaat niet of is verplaatst.',
    },
    419: {
        title: 'Pagina verlopen',
        description:
            'Je sessie is om veiligheidsredenen verlopen. Laad de pagina opnieuw en probeer het nog eens.',
    },
    500: {
        title: 'Er ging iets mis',
        description:
            'Er is een onverwachte fout opgetreden. Die is vastgelegd en we kijken ernaar.',
    },
};

const fallback: ErrorCopy = {
    title: 'Er ging iets mis',
    description: 'De server kon dit verzoek niet afronden.',
};

const current = computed<ErrorCopy>(() => copy[props.status] ?? fallback);

const page = usePage();
const isAuthenticated = computed<boolean>(() => Boolean(page.props.auth?.user));

function reload(): void {
    window.location.reload();
}
</script>

<template>
    <Head :title="`${status} ${current.title}`" />

    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <div
                        class="mb-1 flex h-9 w-9 items-center justify-center rounded-md"
                    >
                        <AppLogoIcon
                            class="size-9 fill-current text-[var(--foreground)] dark:text-white"
                        />
                    </div>
                    <div class="space-y-2 text-center">
                        <p
                            class="text-sm font-medium tracking-widest text-muted-foreground"
                        >
                            {{ status }}
                        </p>
                        <h1 class="text-xl font-medium">{{ current.title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ current.description }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <Button v-if="status === 419" @click="reload">
                        Pagina opnieuw laden
                    </Button>
                    <Button
                        :variant="status === 419 ? 'outline' : 'default'"
                        as-child
                    >
                        <Link
                            :href="isAuthenticated ? recipesIndex() : login()"
                        >
                            {{
                                isAuthenticated
                                    ? 'Terug naar recepten'
                                    : 'Naar inloggen'
                            }}
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
