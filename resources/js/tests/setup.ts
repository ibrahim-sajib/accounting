import { reactive } from 'vue';
import type { Plugin } from 'vue';
import { vi } from 'vitest';

export const formCalls = {
    post: [] as string[],
    put: [] as string[],
};

type RouteFn = typeof globalThis.route;

const rawRoute = (name: string, ..._params: unknown[]): string => `/${name}`;

export const mockRoute: RouteFn = rawRoute as unknown as RouteFn;

globalThis.route = mockRoute;

export const ziggyPlugin: Plugin = {
    install(app) {
        app.config.globalProperties.route = mockRoute;
    },
};

vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@inertiajs/vue3')>();

    const buildForm = (initial: Record<string, unknown> = {}) => {
        const form = reactive<Record<string, unknown> & {
            errors: Record<string, unknown>;
            processing: boolean;
            post: (...a: unknown[]) => void;
            put: (...a: unknown[]) => void;
            patch: (...a: unknown[]) => void;
            get: (...a: unknown[]) => void;
            delete: (...a: unknown[]) => void;
            setError: (...a: unknown[]) => void;
            reset: (...a: unknown[]) => void;
            transform: (...a: unknown[]) => void;
        }>({
            ...initial,
            errors: {},
            processing: false,
            post: (href: unknown) => formCalls.post.push(String(href)),
            put: (href: unknown) => formCalls.put.push(String(href)),
            patch: vi.fn(),
            get: vi.fn(),
            delete: vi.fn(),
            setError: vi.fn(),
            reset: vi.fn(),
            transform: vi.fn(),
        });

        return form;
    };

    return {
        ...actual,
        useForm: buildForm,
    };
});