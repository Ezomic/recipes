<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
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

// In workflow mode the route sends the user through ID to confirm first.
const page = usePage();
const workflowEnabled = computed<boolean>(
    () => page.props.workflow?.enabled ?? false,
);
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="Account verwijderen"
            description="Verwijder je account en alle bijbehorende gegevens"
        />
        <div
            class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
        >
            <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                <p class="font-medium">Waarschuwing</p>
                <p class="text-sm">
                    Let op, dit kan niet ongedaan worden gemaakt.
                </p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="destructive" data-test="delete-user-button"
                        >Account verwijderen</Button
                    >
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        v-slot="{ processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle
                                >Weet je zeker dat je je account wilt
                                verwijderen?</DialogTitle
                            >
                            <DialogDescription>
                                Zodra je account is verwijderd, worden ook alle
                                bijbehorende gegevens definitief verwijderd.
                                <template v-if="workflowEnabled">
                                    Om te bevestigen dat jij het bent, vraagt
                                    Thijssensoftware ID je misschien eerst
                                    opnieuw in te loggen en brengt je daarna
                                    terug om het account te verwijderen.
                                </template>
                            </DialogDescription>
                        </DialogHeader>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    Annuleren
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                            >
                                Account verwijderen
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
