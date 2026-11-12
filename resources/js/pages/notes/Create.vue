<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { create, index, store } from '@/routes/notes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Notities', href: index() },
            { title: 'Nieuw', href: create() },
        ],
    },
});

const form = useForm({ title: '', body: '' });

function submit(): void {
    form.post(store().url);
}
</script>

<template>
    <Head title="Nieuwe notitie" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Nieuwe notitie</h1>

        <form class="flex max-w-xl flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="title">Titel</Label>
                <Input id="title" v-model="form.title" autofocus />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid gap-2">
                <Label for="body">Inhoud</Label>
                <textarea
                    id="body"
                    v-model="form.body"
                    rows="6"
                    class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                />
                <InputError :message="form.errors.body" />
            </div>

            <div class="flex items-center gap-2">
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Notitie aanmaken
                </Button>
                <Button as-child type="button" variant="ghost">
                    <Link :href="index()">Annuleren</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
