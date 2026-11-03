import type * as InertiaVue from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import RecipeForm from '@/components/RecipeForm.vue';
import type { RecipeWithIngredients } from '@/types';

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

const recipe: RecipeWithIngredients = {
    id: 7,
    title: 'Soep',
    steps: ['Snijden', 'Koken'],
    preparation_minutes: 45,
    servings: 6,
    houdbaar: true,
    created_at: '',
    updated_at: '',
    ingredients: [
        { id: 1, position: 0, quantity: 250.5, unit: 'g', name: 'Bloem' },
        { id: 2, position: 1, quantity: null, unit: null, name: 'Zout' },
    ],
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

    it('starts a new recipe with one empty ingredient row', () => {
        const wrapper = mount(RecipeForm);

        expect(wrapper.findAll('[aria-label^="Naam ingrediënt"]')).toHaveLength(
            1,
        );
    });

    it('prefills ingredient rows in order, with blanks for a missing quantity and unit', () => {
        const wrapper = mount(RecipeForm, { props: { recipe } });

        const names = wrapper.findAll<HTMLInputElement>(
            '[aria-label^="Naam ingrediënt"]',
        );
        const quantities = wrapper.findAll<HTMLInputElement>(
            '[aria-label^="Hoeveelheid ingrediënt"]',
        );
        const units = wrapper.findAll('select');
        expect(names.map((input) => input.element.value)).toEqual([
            'Bloem',
            'Zout',
        ]);
        expect(quantities.map((input) => input.element.value)).toEqual([
            '250,5',
            '',
        ]);
        expect(units.map((select) => select.element.value)).toEqual(['g', '']);
    });

    it('starts an existing recipe without ingredients with one empty row', () => {
        const wrapper = mount(RecipeForm, {
            props: { recipe: { ...recipe, ingredients: [] } },
        });

        expect(wrapper.findAll('[aria-label^="Naam ingrediënt"]')).toHaveLength(
            1,
        );
    });

    it('adds and removes ingredient rows, keeping the order of the rest', async () => {
        const wrapper = mount(RecipeForm, { props: { recipe } });

        const addButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Ingrediënt toevoegen');
        await addButton?.trigger('click');
        expect(wrapper.findAll('[aria-label^="Naam ingrediënt"]')).toHaveLength(
            3,
        );

        await wrapper
            .find('[aria-label="Verwijder ingrediënt 1"]')
            .trigger('click');
        const names = wrapper.findAll<HTMLInputElement>(
            '[aria-label^="Naam ingrediënt"]',
        );
        expect(names.map((input) => input.element.value)).toEqual(['Zout', '']);
    });

    it('offers every unit plus a choice for none', () => {
        const wrapper = mount(RecipeForm);

        expect(
            wrapper.findAll('select option').map((option) => option.text()),
        ).toEqual([
            'Geen eenheid',
            'g',
            'kg',
            'ml',
            'l',
            'stuks',
            'eetlepel',
            'theelepel',
        ]);
    });
});
