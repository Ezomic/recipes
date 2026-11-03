<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { MAX_TAG_LENGTH, MAX_TAGS, normaliseTag } from '@/lib/tags';

const props = withDefaults(
    defineProps<{
        suggestions?: string[];
    }>(),
    { suggestions: () => [] },
);

const tags = defineModel<string[]>({ required: true });

const draft = ref('');

const full = computed(() => tags.value.length >= MAX_TAGS);

const offered = computed(() =>
    props.suggestions.filter((name) => !tags.value.includes(name)),
);

function add(raws: string[]): void {
    const next = [...tags.value];

    for (const raw of raws) {
        const name = normaliseTag(raw);

        if (name !== '' && next.length < MAX_TAGS && !next.includes(name)) {
            next.push(name);
        }
    }

    tags.value = next;
}

function remove(name: string): void {
    tags.value = tags.value.filter((tag) => tag !== name);
}

function commit(): void {
    add([draft.value]);
    draft.value = '';
}

function onInput(): void {
    if (!draft.value.includes(',')) {
        return;
    }

    const parts = draft.value.split(',');
    draft.value = parts.pop() ?? '';
    add(parts);
}
</script>

<template>
    <div class="grid gap-2">
        <ul v-if="tags.length > 0" class="flex flex-wrap gap-2">
            <li
                v-for="tag in tags"
                :key="tag"
                data-testid="tag-chip"
                class="inline-flex items-center gap-1 rounded-full bg-secondary px-2.5 py-0.5 text-xs text-secondary-foreground"
            >
                {{ tag }}
                <button
                    type="button"
                    class="rounded-full hover:text-destructive"
                    :aria-label="`Verwijder tag ${tag}`"
                    @click="remove(tag)"
                >
                    <X class="size-3" />
                </button>
            </li>
        </ul>

        <input
            id="new-tag"
            v-model="draft"
            list="tag-suggestions"
            aria-label="Nieuwe tag"
            placeholder="Typ een tag en druk op Enter"
            autocomplete="off"
            :maxlength="MAX_TAG_LENGTH"
            :disabled="full"
            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            @input="onInput"
            @keydown.enter.prevent="commit"
            @blur="commit"
        />
        <datalist id="tag-suggestions">
            <option v-for="name in offered" :key="name" :value="name" />
        </datalist>
    </div>
</template>
