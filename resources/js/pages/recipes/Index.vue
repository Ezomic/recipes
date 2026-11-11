<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import Paginator from '@/components/Paginator.vue';
import RecipeCover from '@/components/RecipeCover.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, show } from '@/routes/recipes';
import type { Paginated, Recipe, RecipeFilters } from '@/types';

const props = defineProps<{
    recipes: Paginated<Recipe>;
    filters: RecipeFilters;
    knownTags: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Recepten', href: index() }],
    },
});

const TYPING_PAUSE = 300;

type Query = {
    q?: string;
    tag?: string[];
    max_minutes?: number;
    houdbaar?: 1;
};

type Changes = {
    q?: string;
    tag?: string[];
    max_minutes?: string;
    houdbaar?: boolean;
};

function minutesText(minutes: number | null): string {
    return minutes === null ? '' : String(minutes);
}

const search = ref(props.filters.q ?? '');
const maxMinutes = ref(minutesText(props.filters.max_minutes));
const timers: { search?: number; minutes?: number } = {};

watch(
    () => props.filters,
    (filters) => {
        if (timers.search !== undefined || timers.minutes !== undefined) {
            return;
        }

        search.value = filters.q ?? '';
        maxMinutes.value = minutesText(filters.max_minutes);
    },
);

onBeforeUnmount(() => {
    window.clearTimeout(timers.search);
    window.clearTimeout(timers.minutes);
});

function visit(changes: Changes): void {
    const q = (changes.q ?? search.value).trim();
    const tag = changes.tag ?? props.filters.tag;
    const minutes = Number(changes.max_minutes ?? maxMinutes.value);
    const query: Query = {};

    if (q !== '') {
        query.q = q;
    }

    if (tag.length > 0) {
        query.tag = tag;
    }

    if (Number.isInteger(minutes) && minutes >= 1) {
        query.max_minutes = minutes;
    }

    if (changes.houdbaar ?? props.filters.houdbaar) {
        query.houdbaar = 1;
    }

    router.get(index().url, query, {
        preserveState: true,
        preserveScroll: true,
    });
}

function onSearch(value: string | number): void {
    search.value = String(value);
    window.clearTimeout(timers.search);
    timers.search = window.setTimeout(() => {
        timers.search = undefined;
        visit({ q: search.value });
    }, TYPING_PAUSE);
}

function onMaxMinutes(value: string | number): void {
    maxMinutes.value = String(value);
    window.clearTimeout(timers.minutes);
    timers.minutes = window.setTimeout(() => {
        timers.minutes = undefined;
        visit({ max_minutes: maxMinutes.value });
    }, TYPING_PAUSE);
}

function toggleTag(name: string): void {
    visit({
        tag: props.filters.tag.includes(name)
            ? props.filters.tag.filter((tag) => tag !== name)
            : [...props.filters.tag, name],
    });
}
</script>

<template>
    <Head title="Recepten" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-8">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-3xl font-semibold md:text-4xl">Recepten</h1>
            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    Nieuw recept
                </Link>
            </Button>
        </div>

        <div
            class="flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-xs"
        >
            <div class="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                <div class="grid gap-2">
                    <Label for="search">Zoeken</Label>
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            id="search"
                            type="search"
                            :model-value="search"
                            placeholder="Titel of ingrediënt"
                            maxlength="100"
                            class="h-10 pl-9"
                            @update:model-value="onSearch"
                        />
                    </div>
                </div>

                <div class="flex items-end gap-4">
                    <div class="grid flex-1 gap-2 sm:w-44 sm:flex-none">
                        <Label for="max-minutes">Maximaal (minuten)</Label>
                        <Input
                            id="max-minutes"
                            :model-value="maxMinutes"
                            inputmode="numeric"
                            class="h-10"
                            @update:model-value="onMaxMinutes"
                        />
                    </div>

                    <div class="flex h-10 items-center gap-2">
                        <Checkbox
                            id="filter-houdbaar"
                            :model-value="filters.houdbaar"
                            @update:model-value="
                                visit({ houdbaar: $event === true })
                            "
                        />
                        <Label for="filter-houdbaar">Houdbaar</Label>
                    </div>
                </div>
            </div>

            <div
                v-if="knownTags.length > 0"
                class="-mx-4 flex [scrollbar-width:none] gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0"
            >
                <button
                    v-for="name in knownTags"
                    :key="name"
                    type="button"
                    data-testid="filter-tag"
                    :aria-pressed="filters.tag.includes(name)"
                    class="shrink-0 rounded-full border bg-background px-3 py-1 text-sm transition-colors hover:bg-accent aria-pressed:border-primary aria-pressed:bg-primary aria-pressed:text-primary-foreground"
                    @click="toggleTag(name)"
                >
                    {{ name }}
                </button>
            </div>
        </div>

        <div
            v-if="recipes.data.length === 0 && filters.active"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center text-muted-foreground"
        >
            <p>Geen recepten gevonden</p>
            <Button as-child variant="outline" size="sm">
                <Link :href="index()">Filters wissen</Link>
            </Button>
        </div>

        <p
            v-else-if="recipes.data.length === 0"
            class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
        >
            Nog geen recepten
        </p>

        <ul v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="recipe in recipes.data" :key="recipe.id">
                <Link
                    :href="show(recipe.id)"
                    class="group flex h-full flex-col overflow-hidden rounded-xl border bg-card shadow-xs transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="relative">
                        <RecipeCover
                            :recipe="recipe"
                            class="aspect-[16/10] transition-transform duration-300 group-hover:scale-[1.02] sm:aspect-[4/3]"
                        />
                        <Badge
                            v-if="recipe.houdbaar"
                            variant="secondary"
                            class="absolute top-3 left-3 shadow-xs"
                        >
                            Houdbaar
                        </Badge>
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <p
                            class="font-serif text-lg leading-snug font-semibold"
                        >
                            {{ recipe.title }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ recipe.preparation_minutes }} min ·
                            {{ recipe.servings }} porties
                        </p>
                        <div
                            v-if="recipe.tags.length > 0"
                            class="mt-auto flex flex-wrap gap-1 pt-1"
                        >
                            <Badge
                                v-for="tag in recipe.tags"
                                :key="tag.id"
                                variant="outline"
                                data-testid="recipe-tag"
                            >
                                {{ tag.name }}
                            </Badge>
                        </div>
                    </div>
                </Link>
            </li>
        </ul>

        <Paginator :meta="recipes" />
    </div>
</template>
