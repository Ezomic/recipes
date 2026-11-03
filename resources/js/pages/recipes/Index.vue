<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import Paginator from '@/components/Paginator.vue';
import RecipePhoto from '@/components/RecipePhoto.vue';
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

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Recepten</h1>
            <Button as-child size="sm">
                <Link :href="create()">
                    <Plus class="size-4" />
                    Nieuw recept
                </Link>
            </Button>
        </div>

        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-end gap-4">
                <div class="grid min-w-48 flex-1 gap-2">
                    <Label for="search">Zoeken</Label>
                    <Input
                        id="search"
                        type="search"
                        :model-value="search"
                        placeholder="Titel of ingrediënt"
                        maxlength="100"
                        @update:model-value="onSearch"
                    />
                </div>

                <div class="grid w-40 gap-2">
                    <Label for="max-minutes">Maximaal (minuten)</Label>
                    <Input
                        id="max-minutes"
                        :model-value="maxMinutes"
                        inputmode="numeric"
                        @update:model-value="onMaxMinutes"
                    />
                </div>

                <div class="flex items-center gap-2 pb-2">
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

            <div v-if="knownTags.length > 0" class="flex flex-wrap gap-2">
                <button
                    v-for="name in knownTags"
                    :key="name"
                    type="button"
                    data-testid="filter-tag"
                    :aria-pressed="filters.tag.includes(name)"
                    class="rounded-full border px-2.5 py-0.5 text-xs aria-pressed:border-primary aria-pressed:bg-primary aria-pressed:text-primary-foreground"
                    @click="toggleTag(name)"
                >
                    {{ name }}
                </button>
            </div>
        </div>

        <div
            v-if="recipes.data.length === 0 && filters.active"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground"
        >
            <p>Geen recepten gevonden</p>
            <Button as-child variant="outline" size="sm">
                <Link :href="index()">Filters wissen</Link>
            </Button>
        </div>

        <p
            v-else-if="recipes.data.length === 0"
            class="rounded-xl border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground"
        >
            Nog geen recepten
        </p>

        <ul v-else class="flex flex-col gap-2">
            <li v-for="recipe in recipes.data" :key="recipe.id">
                <Link
                    :href="show(recipe.id)"
                    class="flex items-center justify-between gap-4 rounded-xl border border-sidebar-border/70 p-4 hover:bg-accent"
                >
                    <RecipePhoto
                        :recipe="recipe"
                        class="size-16 shrink-0 rounded-lg object-cover"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ recipe.title }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ recipe.preparation_minutes }} min ·
                            {{ recipe.servings }} porties
                        </p>
                        <div
                            v-if="recipe.tags.length > 0"
                            class="mt-1 flex flex-wrap gap-1"
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
                    <Badge v-if="recipe.houdbaar" variant="secondary">
                        Houdbaar
                    </Badge>
                </Link>
            </li>
        </ul>

        <Paginator :meta="recipes" />
    </div>
</template>
