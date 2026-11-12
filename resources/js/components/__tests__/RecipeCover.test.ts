import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import RecipeCover from '@/components/RecipeCover.vue';

vi.mock('@/routes/recipes', () => ({
    photo: (id: number) => ({ url: `/recepten/${id}/foto` }),
}));

describe('RecipeCover', () => {
    it('shows the photo when the recipe has one', () => {
        const wrapper = mount(RecipeCover, {
            props: { recipe: { id: 3, title: 'Soep', photo_version: 'abc' } },
        });

        expect(wrapper.find('img').exists()).toBe(true);
        expect(
            wrapper.find('[data-testid="recipe-cover-placeholder"]').exists(),
        ).toBe(false);
    });

    it('shows a placeholder instead of an image without a photo', () => {
        const wrapper = mount(RecipeCover, {
            props: { recipe: { id: 3, title: 'Soep', photo_version: null } },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(
            wrapper.find('[data-testid="recipe-cover-placeholder"]').exists(),
        ).toBe(true);
    });

    it('passes a class on to the wrapper', () => {
        const wrapper = mount(RecipeCover, {
            props: { recipe: { id: 3, title: 'Soep', photo_version: null } },
            attrs: { class: 'aspect-video' },
        });

        expect(wrapper.classes()).toContain('aspect-video');
    });
});
