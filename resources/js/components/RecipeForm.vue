<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, show, store, update } from '@/routes/recipes';
import type { Recipe } from '@/types';

const props = defineProps<{
    recipe?: Recipe;
}>();

const form = useForm({
    title: props.recipe?.title ?? '',
    steps: props.recipe?.steps.length ? [...props.recipe.steps] : [''],
    preparation_minutes: String(props.recipe?.preparation_minutes ?? ''),
    servings: String(props.recipe?.servings ?? ''),
    houdbaar: props.recipe?.houdbaar ?? false,
});

const stepErrors = computed<string[]>(() =>
    Object.entries(form.errors)
        .filter(([key]) => key === 'steps' || key.startsWith('steps.'))
        .map(([, message]) => message),
);

const cancelHref = computed(() =>
    props.recipe ? show(props.recipe.id) : index(),
);

function addStep(): void {
    form.steps.push('');
}

function removeStep(position: number): void {
    form.steps.splice(position, 1);
}

function submit(): void {
    if (props.recipe) {
        form.put(update(props.recipe.id).url);
    } else {
        form.post(store().url);
    }
}
</script>

<template>
    <form class="flex max-w-xl flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="title">Titel</Label>
            <Input id="title" v-model="form.title" autofocus />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label for="preparation_minutes"
                    >Bereidingstijd (minuten)</Label
                >
                <Input
                    id="preparation_minutes"
                    v-model="form.preparation_minutes"
                    inputmode="numeric"
                />
                <InputError :message="form.errors.preparation_minutes" />
            </div>

            <div class="grid gap-2">
                <Label for="servings">Porties</Label>
                <Input
                    id="servings"
                    v-model="form.servings"
                    inputmode="numeric"
                />
                <InputError :message="form.errors.servings" />
            </div>
        </div>

        <div class="flex items-center gap-2">
            <Checkbox id="houdbaar" v-model="form.houdbaar" />
            <Label for="houdbaar">Houdbaar (bewaren zonder koelkast)</Label>
        </div>
        <InputError :message="form.errors.houdbaar" />

        <fieldset class="grid gap-3">
            <legend class="mb-1 text-sm leading-none font-medium">
                Bereiding
            </legend>

            <div
                v-for="(_, position) in form.steps"
                :key="position"
                class="flex items-start gap-2"
            >
                <span
                    class="mt-2 w-6 shrink-0 text-sm text-muted-foreground"
                    aria-hidden="true"
                >
                    {{ position + 1 }}.
                </span>
                <textarea
                    v-model="form.steps[position]"
                    :aria-label="`Stap ${position + 1}`"
                    rows="2"
                    class="min-w-0 flex-1 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :aria-label="`Verwijder stap ${position + 1}`"
                    @click="removeStep(position)"
                >
                    <Trash2 class="size-4" />
                </Button>
            </div>

            <InputError
                v-for="message in stepErrors"
                :key="message"
                :message="message"
            />

            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addStep"
                >
                    <Plus class="size-4" />
                    Stap toevoegen
                </Button>
            </div>
        </fieldset>

        <div class="flex items-center gap-2">
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ recipe ? 'Wijzigingen opslaan' : 'Recept opslaan' }}
            </Button>
            <Button as-child type="button" variant="ghost">
                <Link :href="cancelHref">Annuleren</Link>
            </Button>
        </div>
    </form>
</template>
