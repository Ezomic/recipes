<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import RecipePhoto from '@/components/RecipePhoto.vue';
import TagInput from '@/components/TagInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, show, store, update } from '@/routes/recipes';
import { INGREDIENT_UNITS } from '@/types';
import type { RecipeWithIngredients } from '@/types';

const props = defineProps<{
    recipe?: RecipeWithIngredients;
    knownTags?: string[];
    knownCountries?: string[];
}>();

function blankIngredient(): { quantity: string; unit: string; name: string } {
    return { quantity: '', unit: '', name: '' };
}

const form = useForm({
    title: props.recipe?.title ?? '',
    steps: props.recipe?.steps.length ? [...props.recipe.steps] : [''],
    preparation_minutes: String(props.recipe?.preparation_minutes ?? ''),
    servings: String(props.recipe?.servings ?? ''),
    houdbaar: props.recipe?.houdbaar ?? false,
    country: props.recipe?.country ?? '',
    photo: null as File | null,
    remove_photo: false,
    tags: props.recipe?.tags.map((tag) => tag.name) ?? [],
    ingredients: props.recipe?.ingredients.length
        ? props.recipe.ingredients.map((ingredient) => ({
              quantity:
                  ingredient.quantity === null
                      ? ''
                      : String(ingredient.quantity).replace('.', ','),
              unit: ingredient.unit ?? '',
              name: ingredient.name,
          }))
        : [blankIngredient()],
});

const ingredientErrors = computed<string[]>(() =>
    Object.entries(form.errors)
        .filter(
            ([key]) => key === 'ingredients' || key.startsWith('ingredients.'),
        )
        .map(([, message]) => message),
);

const stepErrors = computed<string[]>(() =>
    Object.entries(form.errors)
        .filter(([key]) => key === 'steps' || key.startsWith('steps.'))
        .map(([, message]) => message),
);

const tagErrors = computed<string[]>(() =>
    Object.entries(form.errors)
        .filter(([key]) => key === 'tags' || key.startsWith('tags.'))
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

function addIngredient(): void {
    form.ingredients.push(blankIngredient());
}

function removeIngredient(position: number): void {
    form.ingredients.splice(position, 1);
}

function moveIngredient(position: number, offset: -1 | 1): void {
    const [moved] = form.ingredients.splice(position, 1);
    form.ingredients.splice(position + offset, 0, moved);
}

function choosePhoto(event: Event): void {
    form.photo = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (form.photo) {
        form.remove_photo = false;
    }
}

function submit(): void {
    if (props.recipe) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            update(props.recipe.id).url,
        );
    } else {
        form.post(store().url);
    }
}
</script>

<template>
    <form
        class="flex flex-col gap-6 rounded-2xl border bg-card p-5 shadow-xs md:p-8"
        @submit.prevent="submit"
    >
        <div class="grid gap-2">
            <Label for="title">Titel</Label>
            <Input id="title" v-model="form.title" autofocus />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid grid-cols-2 items-end gap-4">
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

        <div class="grid gap-2">
            <Label for="country">Land</Label>
            <Input
                id="country"
                v-model="form.country"
                list="known-countries"
                maxlength="60"
                autocomplete="off"
                placeholder="Bijvoorbeeld Frankrijk"
            />
            <datalist id="known-countries">
                <option
                    v-for="country in knownCountries"
                    :key="country"
                    :value="country"
                />
            </datalist>
            <InputError :message="form.errors.country" />
        </div>

        <div class="grid gap-2">
            <Label for="photo">{{
                recipe?.photo_version ? 'Foto vervangen' : 'Foto'
            }}</Label>
            <RecipePhoto
                v-if="recipe?.photo_version"
                :recipe="recipe"
                class="max-h-40 w-fit max-w-full rounded-lg object-cover"
            />
            <input
                id="photo"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="block w-full text-sm file:mr-3 file:rounded-md file:border file:border-input file:bg-transparent file:px-3 file:py-1.5 file:text-sm file:font-medium"
                @change="choosePhoto"
            />
            <InputError :message="form.errors.photo" />
            <div v-if="recipe?.photo_version" class="flex items-center gap-2">
                <Checkbox
                    id="remove_photo"
                    v-model="form.remove_photo"
                    :disabled="form.photo !== null"
                />
                <Label for="remove_photo">Foto verwijderen</Label>
            </div>
            <InputError :message="form.errors.remove_photo" />
        </div>

        <div class="grid gap-2">
            <Label for="new-tag">Tags</Label>
            <TagInput v-model="form.tags" :suggestions="knownTags" />
            <InputError
                v-for="message in tagErrors"
                :key="message"
                :message="message"
            />
        </div>

        <fieldset class="grid gap-3">
            <legend class="mb-1 text-sm leading-none font-medium">
                Ingrediënten
            </legend>

            <div
                v-for="(ingredient, position) in form.ingredients"
                :key="position"
                class="flex flex-wrap items-start gap-2 sm:flex-nowrap"
            >
                <Input
                    v-model="ingredient.quantity"
                    :aria-label="`Hoeveelheid ingrediënt ${position + 1}`"
                    inputmode="decimal"
                    placeholder="Hoeveelheid"
                    class="w-28 shrink-0"
                />
                <select
                    v-model="ingredient.unit"
                    :aria-label="`Eenheid ingrediënt ${position + 1}`"
                    class="h-9 min-w-0 flex-1 shrink-0 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:w-32 sm:flex-none"
                >
                    <option value="">Geen eenheid</option>
                    <option
                        v-for="unit in INGREDIENT_UNITS"
                        :key="unit"
                        :value="unit"
                    >
                        {{ unit }}
                    </option>
                </select>
                <Input
                    v-model="ingredient.name"
                    :aria-label="`Naam ingrediënt ${position + 1}`"
                    placeholder="Naam"
                    class="order-last min-w-0 basis-full sm:order-none sm:flex-1 sm:basis-0"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :aria-label="`Ingrediënt ${position + 1} omhoog verplaatsen`"
                    :disabled="position === 0"
                    @click="moveIngredient(position, -1)"
                >
                    <ChevronUp class="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :aria-label="`Ingrediënt ${position + 1} omlaag verplaatsen`"
                    :disabled="position === form.ingredients.length - 1"
                    @click="moveIngredient(position, 1)"
                >
                    <ChevronDown class="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :aria-label="`Verwijder ingrediënt ${position + 1}`"
                    @click="removeIngredient(position)"
                >
                    <Trash2 class="size-4" />
                </Button>
            </div>

            <InputError
                v-for="message in ingredientErrors"
                :key="message"
                :message="message"
            />

            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addIngredient"
                >
                    <Plus class="size-4" />
                    Ingrediënt toevoegen
                </Button>
            </div>
        </fieldset>

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
