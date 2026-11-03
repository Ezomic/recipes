<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import Paginator from '@/components/Paginator.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/recipes';
import type { Paginated, Recipe } from '@/types';

defineProps<{
    recipes: Paginated<Recipe>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Recepten', href: index() }],
    },
});
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

        <p
            v-if="recipes.data.length === 0"
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
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ recipe.title }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ recipe.preparation_minutes }} min ·
                            {{ recipe.servings }} porties
                        </p>
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
