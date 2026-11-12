import { router } from '@inertiajs/vue3';
import type * as InertiaVue from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
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
    photo: (id: number) => ({ url: `/recepten/${id}/foto` }),
}));

const recipe: RecipeWithIngredients = {
    id: 7,
    title: 'Soep',
    steps: ['Snijden', 'Koken'],
    preparation_minutes: 45,
    servings: 6,
    houdbaar: true,
    photo_version: null,
    created_at: '',
    updated_at: '',
    tags: [
        { id: 1, name: 'soep' },
        { id: 2, name: 'winter' },
    ],
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

    it('moves an ingredient row up and down, taking its quantity and unit along', async () => {
        const wrapper = mount(RecipeForm, { props: { recipe } });
        const names = () =>
            wrapper
                .findAll<HTMLInputElement>('[aria-label^="Naam ingrediënt"]')
                .map((input) => input.element.value);

        await wrapper
            .find('[aria-label="Ingrediënt 2 omhoog verplaatsen"]')
            .trigger('click');
        expect(names()).toEqual(['Zout', 'Bloem']);
        expect(
            wrapper.findAll('select').map((select) => select.element.value),
        ).toEqual(['', 'g']);
        expect(
            wrapper
                .findAll<HTMLInputElement>(
                    '[aria-label^="Hoeveelheid ingrediënt"]',
                )
                .map((input) => input.element.value),
        ).toEqual(['', '250,5']);

        await wrapper
            .find('[aria-label="Ingrediënt 1 omlaag verplaatsen"]')
            .trigger('click');
        expect(names()).toEqual(['Bloem', 'Zout']);
    });

    it('disables moving the first row up and the last row down', () => {
        const wrapper = mount(RecipeForm, { props: { recipe } });

        const disabled = (label: string) =>
            wrapper.find(`[aria-label="${label}"]`).attributes('disabled');
        expect(disabled('Ingrediënt 1 omhoog verplaatsen')).toBeDefined();
        expect(disabled('Ingrediënt 1 omlaag verplaatsen')).toBeUndefined();
        expect(disabled('Ingrediënt 2 omhoog verplaatsen')).toBeUndefined();
        expect(disabled('Ingrediënt 2 omlaag verplaatsen')).toBeDefined();
    });

    it('sends the reordered ingredients in the new order on submit', async () => {
        const wrapper = mount(RecipeForm, { props: { recipe } });
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        await wrapper
            .find('[aria-label="Ingrediënt 1 omlaag verplaatsen"]')
            .trigger('click');
        await wrapper.find('form').trigger('submit');

        const data = visit.mock.calls[0][1]?.data as {
            ingredients: { name: string }[];
        };
        expect(data.ingredients.map((row) => row.name)).toEqual([
            'Zout',
            'Bloem',
        ]);
        visit.mockRestore();
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

    it('starts a new recipe without tags and prefills an existing one', () => {
        expect(
            mount(RecipeForm).findAll('[data-testid="tag-chip"]'),
        ).toHaveLength(0);

        const wrapper = mount(RecipeForm, { props: { recipe } });
        expect(
            wrapper
                .findAll('[data-testid="tag-chip"]')
                .map((chip) => chip.text()),
        ).toEqual(['soep', 'winter']);
    });

    it('passes the known tags on as suggestions', () => {
        const wrapper = mount(RecipeForm, {
            props: { knownTags: ['brood', 'kamperen'] },
        });

        expect(
            wrapper
                .findAll('datalist option')
                .map((o) => o.attributes('value')),
        ).toEqual(['brood', 'kamperen']);
    });

    it('shows a validation error on the tags', async () => {
        const wrapper = mount(RecipeForm, { props: { recipe } });
        (
            wrapper.vm as unknown as {
                form: { setError: (key: string, message: string) => void };
            }
        ).form.setError('tags', 'Te veel tags.');
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Te veel tags.');
    });

    describe('photo', () => {
        const withPhoto = { ...recipe, photo_version: 'abc' };
        const file = new File(['x'], 'foto.png', { type: 'image/png' });

        afterEach(() => {
            vi.restoreAllMocks();
        });

        async function choose(wrapper: ReturnType<typeof mount>) {
            const input = wrapper.find<HTMLInputElement>('#photo');
            Object.defineProperty(input.element, 'files', {
                value: [file],
                configurable: true,
            });
            await input.trigger('change');
        }

        it('offers a file input limited to jpeg, png and webp', () => {
            const input = mount(RecipeForm).find('#photo');

            expect(input.attributes('type')).toBe('file');
            expect(input.attributes('accept')).toBe(
                'image/jpeg,image/png,image/webp',
            );
        });

        it('offers no remove option when there is no photo', () => {
            expect(mount(RecipeForm).find('#remove_photo').exists()).toBe(
                false,
            );
            expect(
                mount(RecipeForm, { props: { recipe } })
                    .find('#remove_photo')
                    .exists(),
            ).toBe(false);
        });

        it('shows the current photo with a remove option on edit', () => {
            const wrapper = mount(RecipeForm, { props: { recipe: withPhoto } });

            expect(wrapper.find('img').attributes('alt')).toBe('Foto van Soep');
            expect(wrapper.text()).toContain('Foto verwijderen');
        });

        it('forgets the remove choice and disables it once a new file is chosen', async () => {
            const wrapper = mount(RecipeForm, { props: { recipe: withPhoto } });
            const remove = wrapper.find('#remove_photo');
            await remove.trigger('click');
            expect(remove.attributes('aria-checked')).toBe('true');

            await choose(wrapper);

            expect(remove.attributes('aria-checked')).toBe('false');
            expect(remove.attributes('disabled')).toBeDefined();
        });

        it('clears the chosen file when the file dialog is cancelled', async () => {
            const wrapper = mount(RecipeForm);
            await choose(wrapper);
            const input = wrapper.find<HTMLInputElement>('#photo');
            Object.defineProperty(input.element, 'files', {
                value: [],
                configurable: true,
            });
            await input.trigger('change');
            const visit = vi
                .spyOn(router, 'visit')
                .mockImplementation(() => {});
            await wrapper.find('form').trigger('submit');

            expect(visit.mock.calls[0][1]?.data).toMatchObject({ photo: null });
        });

        it('posts a new recipe with the chosen file', async () => {
            const wrapper = mount(RecipeForm);
            const visit = vi
                .spyOn(router, 'visit')
                .mockImplementation(() => {});
            await choose(wrapper);
            await wrapper.find('form').trigger('submit');

            expect(visit.mock.calls[0][1]?.method).toBe('post');
            expect(visit.mock.calls[0][1]?.data).toMatchObject({ photo: file });
        });

        it('spoofs PUT through POST on edit so PHP can read the multipart body', async () => {
            const wrapper = mount(RecipeForm, { props: { recipe: withPhoto } });
            const visit = vi
                .spyOn(router, 'visit')
                .mockImplementation(() => {});
            await wrapper.find('form').trigger('submit');

            expect(visit.mock.calls[0][0]).toBe('/recepten/7');
            expect(visit.mock.calls[0][1]?.method).toBe('post');
            expect(visit.mock.calls[0][1]?.data).toMatchObject({
                _method: 'put',
                remove_photo: false,
            });
        });

        it('shows a validation error on the photo', async () => {
            const wrapper = mount(RecipeForm);
            (
                wrapper.vm as unknown as {
                    form: { setError: (key: string, message: string) => void };
                }
            ).form.setError('photo', 'Te groot.');
            await wrapper.vm.$nextTick();

            expect(wrapper.text()).toContain('Te groot.');
        });
    });
});
