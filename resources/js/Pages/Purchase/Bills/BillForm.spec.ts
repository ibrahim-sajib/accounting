import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import BillForm from './BillForm.vue';
import { formCalls, ziggyPlugin } from '@/tests/setup';

const suppliers = [
    { value: 1, label: 'Vendor Inc', payment_terms_days: 0, outstanding: 0 },
    { value: 2, label: 'Net 30 Vendor', payment_terms_days: 30, outstanding: 400 },
];

const products = [
    { value: 1, label: 'Widget', type: 'product', sales_price: 150, purchase_price: 100, tax_rate_id: 1, unit: 'pc' },
    { value: 2, label: 'Service', type: 'service', sales_price: 0, purchase_price: 0, tax_rate_id: null },
];

const taxRates = [{ value: 1, label: 'VAT 15%', rate_percent: 15, is_inclusive: true }];

const mountForm = (props: Record<string, unknown> = {}) =>
    mount(BillForm, {
        props: {
            suppliers,
            products,
            taxRates,
            today: '2026-09-14',
            ...props,
        },
        global: {
            plugins: [ziggyPlugin],
            stubs: { Link: true },
        },
    });

const footerValue = (wrapper: ReturnType<typeof mountForm>, label: string): string => {
    const block = wrapper
        .findAll('div.text-xs.uppercase')
        .find((d) => d.text() === label)!;

    return block.element.parentElement!.querySelector('div.font-mono')!.textContent!.trim();
};

describe('BillForm', () => {
    beforeEach(() => {
        formCalls.post.length = 0;
        formCalls.put.length = 0;
    });

    it('renders a single empty line with zero totals', () => {
        const wrapper = mountForm();

        expect(wrapper.findAll('input[placeholder="Qty"]')).toHaveLength(1);
        expect(footerValue(wrapper, 'Subtotal')).toBe('0.00');
        expect(footerValue(wrapper, 'Total')).toBe('0.00');
    });

    it('selecting a product prefills unit cost and default tax', async () => {
        const wrapper = mountForm();

        await wrapper.findAll('select')[1].setValue('1');
        await nextTick();

        expect((wrapper.find('input[placeholder="Cost"]').element as HTMLInputElement).value).toBe('100');
        expect(footerValue(wrapper, 'Subtotal')).toBe('100.00');
        expect(footerValue(wrapper, 'Tax')).toBe('15.00');
        expect(footerValue(wrapper, 'Total')).toBe('115.00');
    });

    it('recomputes totals when quantity changes', async () => {
        const wrapper = mountForm();

        await wrapper.findAll('select')[1].setValue('1');
        await wrapper.find('input[placeholder="Qty"]').setValue('5');
        await nextTick();

        expect(footerValue(wrapper, 'Subtotal')).toBe('500.00');
        expect(footerValue(wrapper, 'Tax')).toBe('75.00');
        expect(footerValue(wrapper, 'Total')).toBe('575.00');
    });

    it('applies per-line discounts net of tax', async () => {
        const wrapper = mountForm();

        await wrapper.findAll('select')[1].setValue('1');
        await wrapper.find('input[placeholder="Qty"]').setValue('5');
        await wrapper.find('input[placeholder="Disc."]').setValue('20');
        await nextTick();

        expect(footerValue(wrapper, 'Subtotal')).toBe('480.00');
        expect(footerValue(wrapper, 'Discount')).toBe('20.00');
        expect(footerValue(wrapper, 'Tax')).toBe('72.00');
        expect(footerValue(wrapper, 'Total')).toBe('552.00');
    });

    it('adds and removes lines', async () => {
        const wrapper = mountForm();

        await wrapper.findAll('button').find((b) => b.text().includes('Add line'))!.trigger('click');
        expect(wrapper.findAll('input[placeholder="Qty"]')).toHaveLength(2);

        await wrapper.findAll('button[title="Remove line"]')[0].trigger('click');
        expect(wrapper.findAll('input[placeholder="Qty"]')).toHaveLength(1);
    });

    it('auto-fills the due date from the supplier payment terms', async () => {
        const wrapper = mountForm();

        await wrapper.find('#supplier_id').setValue('2');
        await nextTick();

        const expected = () => {
            const date = new Date('2026-09-14T00:00:00');
            date.setDate(date.getDate() + 30);
            return date.toISOString().slice(0, 10);
        };

        expect((wrapper.find('#due_date').element as HTMLInputElement).value).toBe(expected());
    });

    it('posts to the store route on save for a new bill', async () => {
        const wrapper = mountForm();

        await wrapper.find('form').trigger('submit');

        expect(formCalls.post.at(-1)).toBe('/purchase.bills.store');
    });

    it('puts to the update route when a bill id exists', async () => {
        const wrapper = mountForm({
            bill: { id: 9, supplier_id: 1, bill_date: '2026-09-01', due_date: null, reference: null, notes: null },
            lines: [{ product_id: 1, description: '', quantity: '5', unit_cost: '100', discount_amount: '0', tax_rate_id: 1 }],
        });

        expect(wrapper.text()).toContain('Update Draft');

        await wrapper.find('form').trigger('submit');

        expect(formCalls.put.at(-1)).toBe('/purchase.bills.update');
    });
});