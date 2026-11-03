import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import RecipePhoto from '@/components/RecipePhoto.vue';

vi.mock('@/routes/recipes', () => ({
    photo: (id: number, options?: { query?: { v: string } }) => ({
        url: `/recepten/${id}/foto?v=${options?.query?.v}`,
    }),
}));

describe('RecipePhoto', () => {
    it('renders nothing for a recipe without a photo', () => {
        const wrapper = mount(RecipePhoto, {
            props: { recipe: { id: 3, title: 'Soep', photo_version: null } },
        });

        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('renders a lazy loaded image with a versioned url and Dutch alt text', () => {
        const wrapper = mount(RecipePhoto, {
            props: { recipe: { id: 3, title: 'Soep', photo_version: 'abc' } },
        });

        const img = wrapper.find('img');
        expect(img.attributes('src')).toBe('/recepten/3/foto?v=abc');
        expect(img.attributes('alt')).toBe('Foto van Soep');
        expect(img.attributes('loading')).toBe('lazy');
    });

    it('passes a class on to the image', () => {
        const wrapper = mount(RecipePhoto, {
            props: { recipe: { id: 3, title: 'Soep', photo_version: 'abc' } },
            attrs: { class: 'size-16' },
        });

        expect(wrapper.find('img').classes()).toContain('size-16');
    });
});
