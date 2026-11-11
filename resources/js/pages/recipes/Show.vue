<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Clock, Minus, Pencil, Plus, Trash2, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import RecipeCover from '@/components/RecipeCover.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatQuantity, scaleQuantity } from '@/lib/scaling';
import { destroy, edit, index } from '@/routes/recipes';
import type { Ingredient, RecipeWithIngredients } from '@/types';

const props = defineProps<{
    recipe: RecipeWithIngredients;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Recepten', href: index() }],
    },
});

const wantedServings = ref(String(props.recipe.servings));

const servings = computed<number>(() => {
    const wanted = Number(wantedServings.value);

    return Number.isInteger(wanted) && wanted >= 1 && wanted <= 999
        ? wanted
        : props.recipe.servings;
});

function changeServings(step: number): void {
    wantedServings.value = String(servings.value + step);
}

function describe(ingredient: Ingredient): string {
    if (ingredient.quantity === null) {
        return ingredient.name;
    }

    const quantity = formatQuantity(
        scaleQuantity(
            ingredient.quantity,
            ingredient.unit,
            props.recipe.servings,
            servings.value,
        ),
        ingredient.unit,
    );

    return [quantity, ingredient.unit, ingredient.name]
        .filter((part) => part !== null)
        .join(' ');
}

function remove(): void {
    router.delete(destroy(props.recipe.id).url);
}
</script>

<template>
    <Head :title="recipe.title" />

    <article
        class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-8 p-4 md:p-8"
    >
        <RecipeCover
            :recipe="recipe"
            class="aspect-[16/10] max-h-96 w-full rounded-2xl shadow-sm md:aspect-[21/9]"
        />

        <header class="flex flex-col gap-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <h1
                    class="min-w-0 text-3xl leading-tight font-semibold break-words md:text-5xl"
                >
                    {{ recipe.title }}
                </h1>

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
                                    Dit recept wordt definitief verwijderd. Dit
                                    kan niet ongedaan worden gemaakt.
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button variant="secondary">
                                        Annuleren
                                    </Button>
                                </DialogClose>
                                <Button variant="destructive" @click="remove">
                                    Definitief verwijderen
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <p class="flex items-center gap-4 text-muted-foreground">
                    <span class="inline-flex items-center gap-1.5">
                        <Clock class="size-4" aria-hidden="true" />
                        {{ recipe.preparation_minutes }} minuten
                    </span>
                    <span>·</span>
                    <span class="inline-flex items-center gap-1.5">
                        <Users class="size-4" aria-hidden="true" />
                        {{ recipe.servings }} porties
                    </span>
                </p>
                <div class="flex flex-wrap gap-2">
                    <Badge v-if="recipe.houdbaar" variant="secondary">
                        Houdbaar
                    </Badge>
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
        </header>

        <div
            class="grid gap-8 lg:grid-cols-[22rem_1fr] lg:items-start lg:gap-12"
        >
            <section
                class="flex flex-col gap-4 rounded-2xl border bg-card p-5 shadow-xs lg:sticky lg:top-4"
            >
                <h2 class="text-2xl font-semibold">Ingrediënten</h2>

                <div
                    class="flex items-center justify-between gap-3 rounded-xl bg-secondary p-3"
                >
                    <Label for="wanted-servings" class="text-base">
                        Porties
                    </Label>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-11 rounded-full"
                            aria-label="Minder porties"
                            :disabled="servings <= 1"
                            @click="changeServings(-1)"
                        >
                            <Minus class="size-5" />
                        </Button>
                        <Input
                            id="wanted-servings"
                            v-model="wantedServings"
                            type="number"
                            min="1"
                            max="999"
                            step="1"
                            inputmode="numeric"
                            class="h-11 w-16 [appearance:textfield] text-center text-xl font-semibold [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-11 rounded-full"
                            aria-label="Meer porties"
                            :disabled="servings >= 999"
                            @click="changeServings(1)"
                        >
                            <Plus class="size-5" />
                        </Button>
                    </div>
                </div>

                <p
                    v-if="recipe.ingredients.length === 0"
                    class="text-muted-foreground"
                >
                    Geen ingrediënten.
                </p>
                <ul v-else class="divide-y text-lg">
                    <li
                        v-for="ingredient in recipe.ingredients"
                        :key="ingredient.id"
                        class="py-2.5"
                    >
                        {{ describe(ingredient) }}
                    </li>
                </ul>
            </section>

            <section class="flex flex-col gap-4">
                <h2 class="text-2xl font-semibold">Bereiding</h2>
                <p
                    v-if="recipe.steps.length === 0"
                    class="text-muted-foreground"
                >
                    Geen bereidingsstappen.
                </p>
                <ol v-else class="flex flex-col gap-6">
                    <li
                        v-for="(step, position) in recipe.steps"
                        :key="position"
                        class="flex gap-4"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary font-serif text-lg font-semibold text-primary-foreground"
                            aria-hidden="true"
                        >
                            {{ position + 1 }}
                        </span>
                        <span
                            class="min-w-0 pt-1 text-xl leading-relaxed whitespace-pre-line"
                        >
                            {{ step }}
                        </span>
                    </li>
                </ol>
            </section>
        </div>
    </article>
</template>
