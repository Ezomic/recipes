<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { destroy, edit, index } from '@/routes/recipes';
import type { Recipe } from '@/types';

const props = defineProps<{
    recipe: Recipe;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Recepten', href: index() }],
    },
});

function remove(): void {
    router.delete(destroy(props.recipe.id).url);
}
</script>

<template>
    <Head :title="recipe.title" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold break-words">
                    {{ recipe.title }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ recipe.preparation_minutes }} minuten ·
                    {{ recipe.servings }} porties
                </p>
                <Badge v-if="recipe.houdbaar" variant="secondary" class="mt-2">
                    Houdbaar
                </Badge>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <Button as-child variant="outline" size="sm">
                    <Link :href="edit(recipe.id)">
                        <Pencil class="size-4" />
                        Bewerken
                    </Link>
                </Button>

                <Dialog>
                    <DialogTrigger as-child>
                        <Button variant="destructive" size="sm">
                            <Trash2 class="size-4" />
                            Verwijderen
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Recept verwijderen?</DialogTitle>
                            <DialogDescription>
                                Dit recept wordt definitief verwijderd. Dit kan
                                niet ongedaan worden gemaakt.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary">Annuleren</Button>
                            </DialogClose>
                            <Button variant="destructive" @click="remove">
                                Definitief verwijderen
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <section>
            <h2 class="mb-2 font-medium">Bereiding</h2>
            <p
                v-if="recipe.steps.length === 0"
                class="text-sm text-muted-foreground"
            >
                Geen bereidingsstappen.
            </p>
            <ol v-else class="list-decimal space-y-2 pl-6">
                <li
                    v-for="(step, position) in recipe.steps"
                    :key="position"
                    class="whitespace-pre-line"
                >
                    {{ step }}
                </li>
            </ol>
        </section>
    </div>
</template>
