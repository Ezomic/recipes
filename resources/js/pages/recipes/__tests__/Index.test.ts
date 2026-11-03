import { router } from '@inertiajs/vue3';
import type * as InertiaVue from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Index from '@/pages/recipes/Index.vue';
import type { Paginated, Recipe, RecipeFilters } from '@/types';

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<typeof InertiaVue>()),
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    Head: { template: '<span />' },
    router: { get: vi.fn() },
}));

vi.mock('@/routes/recipes', () => ({
    index: () => ({ url: '/recepten', toString: () => '/recepten' }),
    create: () => '/recepten/create',
    show: (id: number) => `/recepten/${id}`,
    photo: (id: number) => ({ url: `/recepten/${id}/foto` }),
}));

const soup: Recipe = {
    id: 1,
    title: 'Soep',
    steps: [],
    preparation_minutes: 20,
    servings: 4,
    houdbaar: true,
    photo_version: null,
    created_at: '',
    updated_at: '',
    tags: [
        { id: 1, name: 'kamperen' },
        { id: 2, name: 'eenpans' },
    ],
};

const noFilters: RecipeFilters = {
    q: null,
    tag: [],
    max_minutes: null,
    houdbaar: false,
    active: false,
};

function page(data: Recipe[]): Paginated<Recipe> {
    return {
        data,
        current_page: 1,
        first_page_url: '',
        from: 1,
        last_page: 1,
        last_page_url: '',
        links: [],
        next_page_url: null,
        path: '/recepten',
        per_page: 15,
        prev_page_url: null,
        to: data.length,
        total: data.length,
    };
}

function render(
    data: Recipe[] = [soup],
    filters: Partial<RecipeFilters> = {},
    knownTags: string[] = ['brood', 'eenpans', 'kamperen'],
) {
    return mount(Index, {
        props: {
            recipes: page(data),
            filters: { ...noFilters, ...filters },
            knownTags,
        },
    });
}

const getMock = vi.mocked(router.get);

beforeEach(() => {
    vi.useFakeTimers();
    getMock.mockClear();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('recipes/Index', () => {
    it('lists recipes with their tags as chips', () => {
        const wrapper = render();

        expect(wrapper.text()).toContain('Soep');
        const chips = wrapper.findAll('[data-testid="recipe-tag"]');
        expect(chips.map((chip) => chip.text())).toEqual([
            'kamperen',
            'eenpans',
        ]);
    });

    it('shows the plain empty state when there are no recipes and no filters', () => {
        const wrapper = render([]);

        expect(wrapper.text()).toContain('Nog geen recepten');
        expect(wrapper.text()).not.toContain('Geen recepten gevonden');
    });

    it('shows a no-results state with a way to clear the filters', () => {
        const wrapper = render([], { q: 'xyz', active: true });

        expect(wrapper.text()).toContain('Geen recepten gevonden');
        expect(wrapper.text()).not.toContain('Nog geen recepten');
        const clear = wrapper
            .findAll('a')
            .find((link) => link.text() === 'Filters wissen');
        expect(clear?.attributes('href')).toBe('/recepten');
    });

    it('offers no clear button while no filter is active', () => {
        const wrapper = render();

        expect(wrapper.text()).not.toContain('Filters wissen');
    });

    it('fills the controls from the applied filters', () => {
        const wrapper = render([soup], {
            q: 'soep',
            tag: ['kamperen'],
            max_minutes: 30,
            houdbaar: true,
            active: true,
        });

        expect(wrapper.find<HTMLInputElement>('#search').element.value).toBe(
            'soep',
        );
        expect(
            wrapper.find<HTMLInputElement>('#max-minutes').element.value,
        ).toBe('30');
        expect(
            wrapper.find('#filter-houdbaar').attributes('aria-checked'),
        ).toBe('true');
        const pressed = wrapper
            .findAll('[data-testid="filter-tag"]')
            .filter((button) => button.attributes('aria-pressed') === 'true');
        expect(pressed.map((button) => button.text())).toEqual(['kamperen']);
    });

    it('searches after a pause in typing, not on every key', async () => {
        const wrapper = render();

        await wrapper.find('#search').setValue('s');
        await wrapper.find('#search').setValue('so');
        vi.advanceTimersByTime(299);
        expect(getMock).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1);
        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock).toHaveBeenCalledWith(
            '/recepten',
            { q: 'so' },
            expect.objectContaining({
                preserveState: true,
                preserveScroll: true,
            }),
        );
    });

    it('drops a blank search from the query', async () => {
        const wrapper = render([soup], { q: 'soep', active: true });

        await wrapper.find('#search').setValue('   ');
        vi.advanceTimersByTime(300);

        expect(getMock).toHaveBeenCalledWith(
            '/recepten',
            {},
            expect.anything(),
        );
    });

    it('adds and removes a tag filter at once', async () => {
        const wrapper = render();
        const buttons = wrapper.findAll('[data-testid="filter-tag"]');

        await buttons[2].trigger('click');
        expect(getMock).toHaveBeenLastCalledWith(
            '/recepten',
            { tag: ['kamperen'] },
            expect.anything(),
        );

        getMock.mockClear();
        const active = render([soup], { tag: ['kamperen'], active: true });
        await active.findAll('[data-testid="filter-tag"]')[2].trigger('click');
        expect(getMock).toHaveBeenLastCalledWith(
            '/recepten',
            {},
            expect.anything(),
        );
    });

    it('keeps the other filters when one changes', async () => {
        const wrapper = render([soup], {
            q: 'soep',
            tag: ['eenpans'],
            max_minutes: 30,
            active: true,
        });

        await wrapper.find('#filter-houdbaar').trigger('click');

        expect(getMock).toHaveBeenLastCalledWith(
            '/recepten',
            { q: 'soep', tag: ['eenpans'], max_minutes: 30, houdbaar: 1 },
            expect.anything(),
        );
    });

    it('filters on maximum minutes after a pause, ignoring non-numbers', async () => {
        const wrapper = render();

        await wrapper.find('#max-minutes').setValue('45');
        vi.advanceTimersByTime(300);
        expect(getMock).toHaveBeenLastCalledWith(
            '/recepten',
            { max_minutes: 45 },
            expect.anything(),
        );

        await wrapper.find('#max-minutes').setValue('abc');
        vi.advanceTimersByTime(300);
        expect(getMock).toHaveBeenLastCalledWith(
            '/recepten',
            {},
            expect.anything(),
        );
    });

    it('follows the applied filters when the page props change, as on the back button', async () => {
        const wrapper = render([soup], { q: 'soep', active: true });

        await wrapper.setProps({ filters: { ...noFilters } });
        await flushPromises();

        expect(wrapper.find<HTMLInputElement>('#search').element.value).toBe(
            '',
        );
        expect(
            wrapper.find<HTMLInputElement>('#max-minutes').element.value,
        ).toBe('');
    });

    it('shows a thumbnail only for recipes with a photo', () => {
        const wrapper = render([
            soup,
            { ...soup, id: 2, photo_version: 'abc' },
        ]);

        expect(wrapper.findAll('img')).toHaveLength(1);
        expect(wrapper.find('img').attributes('src')).toBe('/recepten/2/foto');
    });
});
