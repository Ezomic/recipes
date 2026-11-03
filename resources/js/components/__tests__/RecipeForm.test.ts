import type * as InertiaVue from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import RecipeForm from '@/components/RecipeForm.vue';
import type { Recipe } from '@/types';

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof InertiaVue>()),
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

vi.mock('@/routes/recipes', () => ({
    index: () => '/recepten',
    show: (id: number) => `/recepten/${id}`,
    store: () => ({ url: '/recepten' }),
    update: (id: number) => ({ url: `/recepten/${id}` }),
}));

const recipe: Recipe = {
    id: 7,
    title: 'Soep',
    steps: ['Snijden', 'Koken'],
    preparation_minutes: 45,
    servings: 6,
    houdbaar: true,
    created_at: '',
    updated_at: '',
};

function stepBoxes(wrapper: ReturnType<typeof mount>) {
    return wrapper.findAll('textarea');
}

describe('RecipeForm', () => {
    it('starts a new recipe with one empty step', () => {
        const wrapper = mount(RecipeForm);

        expect(stepBoxes(wrapper)).toHaveLength(1);
        expect(wrapper.text()).toContain('Recept opslaan');
    });

    it('prefills an existing recipe, one box per step', () => {
        const wrapper = mount(RecipeForm, { props: { recipe } });

        expect(stepBoxes(wrapper).map((box) => box.element.value)).toEqual([
            'Snijden',
            'Koken',
        ]);
        expect(wrapper.text()).toContain('Wijzigingen opslaan');
    });

    it('adds and removes steps', async () => {
        const wrapper = mount(RecipeForm, { props: { recipe } });

        const addButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Stap toevoegen');
        await addButton?.trigger('click');
        expect(stepBoxes(wrapper)).toHaveLength(3);

        await wrapper.find('[aria-label="Verwijder stap 1"]').trigger('click');
        expect(stepBoxes(wrapper).map((box) => box.element.value)).toEqual([
            'Koken',
            '',
        ]);
    });
});
