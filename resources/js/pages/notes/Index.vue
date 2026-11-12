<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import Paginator from '@/components/Paginator.vue';
import { Button } from '@/components/ui/button';
import { create, destroy, edit, index } from '@/routes/notes';
import type { Note, Paginated } from '@/types';

defineProps<{
    notes: Paginated<Note>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notities', href: index() }],
    },
});

function remove(note: Note): void {
    router.delete(destroy(note.id).url);
}
</script>

<template>
    <Head title="Notities" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Notities</h1>
            <Button as-child size="sm">
                <Link :href="create()">
                    <Plus class="size-4" />
                    Nieuwe notitie
                </Link>
            </Button>
        </div>

        <p
            v-if="notes.data.length === 0"
            class="rounded-xl border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground"
        >
            Nog geen notities. Maak je eerste aan.
        </p>

        <ul v-else class="flex flex-col gap-2">
            <li
                v-for="note in notes.data"
                :key="note.id"
                class="flex items-start justify-between gap-4 rounded-xl border border-sidebar-border/70 p-4"
            >
                <div class="min-w-0">
                    <p class="font-medium">{{ note.title }}</p>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ note.body }}
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <Button as-child variant="ghost" size="icon">
                        <Link
                            :href="edit(note.id)"
                            aria-label="Notitie bewerken"
                        >
                            <Pencil class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Notitie verwijderen"
                        @click="remove(note)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </li>
        </ul>

        <Paginator :meta="notes" />
    </div>
</template>
