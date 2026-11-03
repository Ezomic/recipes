import type * as InertiaVue from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Show from '@/pages/recipes/Show.vue';
import type { RecipeWithIngredients } from '@/types';

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof InertiaVue>()),
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    Head: { template: '<span />' },
}));

vi.mock('@/routes/recipes', () => ({
    index: () => '/recepten',
    edit: (id: number) => `/recepten/${id}/edit`,
    destroy: (id: number) => ({ url: `/recepten/${id}` }),
}));

const recipe: RecipeWithIngredients = {
    id: 7,
    title: 'Soep',
    steps: ['Koken'],
    preparation_minutes: 45,
    servings: 4,
    houdbaar: false,
    created_at: '',
    updated_at: '',
    tags: [
        { id: 1, name: 'soep' },
        { id: 2, name: 'winter' },
    ],
    ingredients: [
        { id: 1, position: 0, quantity: 250, unit: 'g', name: 'Bloem' },
        { id: 2, position: 1, quantity: 1, unit: 'eetlepel', name: 'Olie' },
        { id: 3, position: 2, quantity: 2, unit: 'stuks', name: 'Eieren' },
        {
            id: 4,
            position: 3,
            quantity: null,
            unit: null,
            name: 'Zout naar smaak',
        },
    ],
};

function items(wrapper: ReturnType<typeof mount>): string[] {
    return wrapper.findAll('li').map((li) => li.text());
}

async function setServings(wrapper: ReturnType<typeof mount>, value: string) {
    await wrapper.find('#wanted-servings').setValue(value);
}

describe('recipes/Show', () => {
    it('lists the ingredients as stored at the recipe servings', () => {
        const wrapper = mount(Show, { props: { recipe } });

        expect(items(wrapper).slice(0, 4)).toEqual([
            '250 g Bloem',
            '1 eetlepel Olie',
            '2 stuks Eieren',
            'Zout naar smaak',
        ]);
    });

    it('halves and doubles the quantities', async () => {
        const wrapper = mount(Show, { props: { recipe } });

        await setServings(wrapper, '2');
        expect(items(wrapper).slice(0, 4)).toEqual([
            '125 g Bloem',
            '½ eetlepel Olie',
            '1 stuks Eieren',
            'Zout naar smaak',
        ]);

        await setServings(wrapper, '8');
        expect(items(wrapper).slice(0, 4)).toEqual([
            '500 g Bloem',
            '2 eetlepel Olie',
            '4 stuks Eieren',
            'Zout naar smaak',
        ]);
    });

    it('rounds an awkward ratio sensibly', async () => {
        const wrapper = mount(Show, { props: { recipe } });

        await setServings(wrapper, '3');

        expect(items(wrapper).slice(0, 3)).toEqual([
            '188 g Bloem',
            '¾ eetlepel Olie',
            '1½ stuks Eieren',
        ]);
    });

    it.each(['', '0', '-2', '1.5', 'abc', '1000'])(
        'falls back to the recipe servings for %j',
        async (value) => {
            const wrapper = mount(Show, { props: { recipe } });

            await setServings(wrapper, value);

            expect(items(wrapper)[0]).toBe('250 g Bloem');
        },
    );

    it('says so when the recipe has no ingredients', () => {
        const wrapper = mount(Show, {
            props: { recipe: { ...recipe, ingredients: [] } },
        });

        expect(wrapper.text()).toContain('Geen ingrediënten.');
    });

    it('shows the tags as chips and nothing when there are none', () => {
        const wrapper = mount(Show, { props: { recipe } });
        expect(
            wrapper
                .findAll('[data-testid="recipe-tag"]')
                .map((chip) => chip.text()),
        ).toEqual(['soep', 'winter']);

        const bare = mount(Show, {
            props: { recipe: { ...recipe, tags: [] } },
        });
        expect(bare.findAll('[data-testid="recipe-tag"]')).toHaveLength(0);
    });
});
