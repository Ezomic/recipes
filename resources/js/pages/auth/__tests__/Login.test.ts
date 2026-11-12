import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Login from '@/pages/auth/Login.vue';

const state = vi.hoisted(() => ({
    workflowEnabled: false,
    setLayoutProps: vi.fn(),
    errors: {} as Record<string, string>,
}));

vi.mock('@inertiajs/vue3', () => ({
    Form: {
        props: ['action', 'method'],
        setup: () => ({ state }),
        template:
            '<form :data-action="action"><slot :errors="state.errors" :processing="false" /></form>',
    },
    Head: { template: '<span />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    setLayoutProps: state.setLayoutProps,
    usePage: () => ({
        props: { workflow: { enabled: state.workflowEnabled } },
    }),
}));

vi.mock('@/routes/login', () => ({
    store: { form: () => ({ action: '/login', method: 'post' }) },
}));
vi.mock('@/routes/login/code', () => ({
    send: { form: () => ({ action: '/login/code', method: 'post' }) },
}));
vi.mock('@/routes/sso', () => ({
    redirect: () => ({ url: '/auth/sso/redirect' }),
}));

function layoutProps(): Record<string, unknown> {
    const { layout } = Login as { layout?: Record<string, unknown> };

    return Object.assign(
        { ...layout },
        ...state.setLayoutProps.mock.calls.map(([props]) => props),
    );
}

function mountLogin(email: string | null = null) {
    return mount(Login, {
        props: { email },
        global: { stubs: { PasskeyVerify: true } },
    });
}

describe('Login', () => {
    beforeEach(() => {
        state.workflowEnabled = false;
        state.setLayoutProps.mockClear();
        state.errors = {};
    });

    it('submits the remember checkbox under the name the server reads', async () => {
        const wrapper = mountLogin('robbin@example.com');
        const form = wrapper.get('form').element as HTMLFormElement;

        expect(new FormData(form).has('remember')).toBe(false);

        await wrapper.get('#remember').trigger('click');

        expect(new FormData(form).get('remember')).toBe('on');
    });

    it('shows an error for a field that has no place of its own', () => {
        state.errors = {
            code: 'Deze code is onjuist of verlopen.',
            remember: 'Ongeldige keuze.',
        };

        const wrapper = mountLogin('robbin@example.com');
        const shown = wrapper
            .findAll('p')
            .map((paragraph) => paragraph.text())
            .filter((text) => text === 'Ongeldige keuze.');

        expect(shown).toHaveLength(1);
        expect(wrapper.text()).toContain('Deze code is onjuist of verlopen.');
        expect(
            wrapper
                .findAll('p')
                .filter(
                    (paragraph) =>
                        paragraph.text() ===
                        'Deze code is onjuist of verlopen.',
                ),
        ).toHaveLength(1);
    });

    it('first asks only for the email address', () => {
        const wrapper = mountLogin();

        expect(wrapper.find('input[name="email"]').exists()).toBe(true);
        expect(wrapper.find('input[name="code"]').exists()).toBe(false);
        expect(wrapper.find('input[name="password"]').exists()).toBe(false);
        expect(wrapper.get('form').attributes('data-action')).toBe(
            '/login/code',
        );
        expect(layoutProps()).toMatchObject({
            title: 'Log in op je account',
            description:
                'Vul je e-mailadres in, dan sturen we je een inlogcode',
        });
    });

    it('has no password field and no reset link', () => {
        const text = mountLogin().text();

        expect(text).not.toContain('Wachtwoord');
    });

    it('keeps the passkey button, in Dutch', () => {
        const wrapper = mountLogin();
        const passkey = wrapper.findComponent({ name: 'PasskeyVerify' });

        expect(passkey.exists()).toBe(true);
        expect(passkey.attributes('label')).toBe('Inloggen met een passkey');
        expect(passkey.attributes('separator')).toBe('Of ga verder met e-mail');
    });

    it('asks for the six digit code once one was sent', () => {
        const wrapper = mountLogin('robbin@example.com');
        const code = wrapper.get('input[name="code"]');

        expect(wrapper.get('form').attributes('data-action')).toBe('/login');
        expect(code.attributes('inputmode')).toBe('numeric');
        expect(code.attributes('autocomplete')).toBe('one-time-code');
        expect(code.attributes('maxlength')).toBe('6');
        expect(wrapper.text()).toContain('robbin@example.com');
        expect(wrapper.text()).toContain('Onthoud mij');
        expect(layoutProps()).toMatchObject({
            description: 'Vul de code in die we naar je gemaild hebben',
        });
    });

    it('sends the address along with the code', () => {
        const wrapper = mountLogin('robbin@example.com');

        expect(wrapper.get('input[name="email"]').attributes('type')).toBe(
            'hidden',
        );
        expect(wrapper.get('input[name="email"]').element).toHaveProperty(
            'value',
            'robbin@example.com',
        );
    });

    it('keeps only digits and at most six of them', async () => {
        const wrapper = mountLogin('robbin@example.com');
        const code = wrapper.get('input[name="code"]');

        await code.setValue('12 34-5a678');

        expect((code.element as HTMLInputElement).value).toBe('123456');
    });

    it('offers a new code and a way back to another address', async () => {
        const wrapper = mountLogin('robbin@example.com');

        expect(wrapper.text()).toContain('Stuur een nieuwe code');

        await wrapper.get('[data-test="change-email"]').trigger('click');

        expect(wrapper.find('input[name="code"]').exists()).toBe(false);
        expect(wrapper.find('input[name="email"]').attributes('type')).toBe(
            'email',
        );
    });

    it('points to the Thijssensoftware account in workflow mode', () => {
        state.workflowEnabled = true;

        const wrapper = mountLogin();

        expect(wrapper.find('input[name="email"]').exists()).toBe(false);
        expect(wrapper.get('a').attributes('href')).toBe('/auth/sso/redirect');
        expect(layoutProps()).toMatchObject({
            description: 'Log in met je Thijssensoftware-account',
        });
    });
});
