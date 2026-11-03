import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TagInput from '@/components/TagInput.vue';

function mountInput(modelValue: string[] = [], suggestions: string[] = []) {
    return mount(TagInput, {
        props: {
            modelValue,
            suggestions,
            'onUpdate:modelValue': (value: string[]) =>
                wrapper.setProps({ modelValue: value }),
        },
    });
}

let wrapper: ReturnType<typeof mountInput>;

function field() {
    return wrapper.find<HTMLInputElement>('input');
}

function chips(): string[] {
    return wrapper.findAll('li').map((li) => li.text());
}

describe('TagInput', () => {
    it('shows the current tags as chips', () => {
        wrapper = mountInput(['soep', 'brood']);

        expect(chips()).toEqual(['soep', 'brood']);
    });

    it('adds a normalised tag on Enter and clears the field', async () => {
        wrapper = mountInput();

        await field().setValue('  Kamperen ');
        await field().trigger('keydown', { key: 'Enter' });

        expect(chips()).toEqual(['kamperen']);
        expect(field().element.value).toBe('');
    });

    it('does not submit the surrounding form on Enter', async () => {
        wrapper = mountInput();
        await field().setValue('soep');

        const event = new KeyboardEvent('keydown', {
            key: 'Enter',
            cancelable: true,
        });
        field().element.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });

    it('adds a tag when a comma is typed', async () => {
        wrapper = mountInput();

        await field().setValue('eenpans,');

        expect(chips()).toEqual(['eenpans']);
        expect(field().element.value).toBe('');
    });

    it('splits pasted comma separated text and keeps the tail in the field', async () => {
        wrapper = mountInput();

        await field().setValue('a, b,c');

        expect(chips()).toEqual(['a', 'b']);
        expect(field().element.value).toBe('c');
    });

    it('adds the pending text on blur so it is not lost on save', async () => {
        wrapper = mountInput();

        await field().setValue('winter');
        await field().trigger('blur');

        expect(chips()).toEqual(['winter']);
    });

    it('ignores blank text, duplicates and case variants', async () => {
        wrapper = mountInput(['soep']);

        await field().setValue('   ');
        await field().trigger('keydown', { key: 'Enter' });
        await field().setValue('SOEP');
        await field().trigger('keydown', { key: 'Enter' });

        expect(chips()).toEqual(['soep']);
        expect(field().element.value).toBe('');
    });

    it('stops at twenty tags', async () => {
        const twenty = Array.from({ length: 20 }, (_, n) => `tag${n}`);
        wrapper = mountInput(twenty);

        await field().setValue('extra');
        await field().trigger('keydown', { key: 'Enter' });

        expect(chips()).toHaveLength(20);
        expect(field().attributes('disabled')).toBeDefined();
    });

    it('limits the length of a tag in the field', () => {
        wrapper = mountInput();

        expect(field().attributes('maxlength')).toBe('50');
    });

    it('removes a tag with its button', async () => {
        wrapper = mountInput(['soep', 'brood']);

        await wrapper
            .find('[aria-label="Verwijder tag soep"]')
            .trigger('click');

        expect(chips()).toEqual(['brood']);
    });

    it('offers suggestions that are not chosen yet', () => {
        wrapper = mountInput(['soep'], ['soep', 'brood', 'kamperen']);

        expect(
            wrapper
                .findAll('datalist option')
                .map((o) => o.attributes('value')),
        ).toEqual(['brood', 'kamperen']);
    });
});
