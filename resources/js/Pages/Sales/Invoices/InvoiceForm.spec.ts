import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import InvoiceForm from './InvoiceForm.vue';
import { formCalls, ziggyPlugin } from '@/tests/setup';

const customers = [
    { value: 1, label: 'Buyer Co', credit_limit: 0, payment_terms_days: 0, outstanding: 0 },
    { value: 2, label: 'Net 30 Co', credit_limit: 5000, payment_terms_days: 30, outstanding: 200 },
];

const products = [
    { value: 1, label: 'Widget', type: 'product', sales_price: 150, purchase_price: 100, tax_rate_id: 1, unit: 'pc' },
    { value: 2, label: 'Consulting', type: 'service', sales_price: 50, purchase_price: 0, tax_rate_id: null },
];

const taxRates = [{ value: 1, label: 'VAT 15%', rate_percent: 15, is_inclusive: true }];

const mountForm = (props: Record<string, unknown> = {}) =>
    mount(InvoiceForm, {
        props: {
            customers,
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

describe('InvoiceForm', () => {
    beforeEach(() => {
        formCalls.post.length = 0;
        formCalls.put.length = 0;
    });

    it('renders a single empty line with zero totals', () => {
        const wrapper = mountForm();

        expect(wrapper.findAll('input[placeholder="Qty"]')).toHaveLength(1);
        expect(footerValue(wrapper, 'Subtotal')).toBe('0.00');
        expect(footerValue(wrapper, 'Discount')).toBe('0.00');
        expect(footerValue(wrapper, 'Tax')).toBe('0.00');
        expect(footerValue(wrapper, 'Total')).toBe('0.00');
    });

    it('selecting a product prefills price and default tax rate', async () => {
        const wrapper = mountForm();

        const selects = wrapper.findAll('select');
        await selects[1].setValue('1');
        await nextTick();

        expect((wrapper.find('input[placeholder="Price"]').element as HTMLInputElement).value).toBe('150');
        expect(footerValue(wrapper, 'Subtotal')).toBe('150.00');
        expect(footerValue(wrapper, 'Tax')).toBe('22.50');
        expect(footerValue(wrapper, 'Total')).toBe('172.50');
    });

    it('recomputes totals when quantity changes', async () => {
        const wrapper = mountForm();

        await wrapper.findAll('select')[1].setValue('1');
        await wrapper.find('input[placeholder="Qty"]').setValue('2');
        await nextTick();

        expect(footerValue(wrapper, 'Subtotal')).toBe('300.00');
        expect(footerValue(wrapper, 'Tax')).toBe('45.00');
        expect(footerValue(wrapper, 'Total')).toBe('345.00');
    });

    it('applies per-line discounts net of tax', async () => {
        const wrapper = mountForm();

        await wrapper.findAll('select')[1].setValue('1');
        await wrapper.find('input[placeholder="Qty"]').setValue('2');
        await wrapper.find('input[placeholder="Disc."]').setValue('10');
        await nextTick();

        expect(footerValue(wrapper, 'Subtotal')).toBe('290.00');
        expect(footerValue(wrapper, 'Discount')).toBe('10.00');
        expect(footerValue(wrapper, 'Tax')).toBe('43.50');
        expect(footerValue(wrapper, 'Total')).toBe('333.50');
    });

    it('adds and removes lines', async () => {
        const wrapper = mountForm();

        await wrapper.findAll('button').find((b) => b.text().includes('Add line'))!.trigger('click');
        expect(wrapper.findAll('input[placeholder="Qty"]')).toHaveLength(2);

        await wrapper.findAll('button[title="Remove line"]')[0].trigger('click');
        expect(wrapper.findAll('input[placeholder="Qty"]')).toHaveLength(1);
    });

    it('auto-fills the due date from the customer payment terms', async () => {
        const wrapper = mountForm();

        await wrapper.find('#customer_id').setValue('2');
        await nextTick();

        const expected = () => {
            const date = new Date('2026-09-14T00:00:00');
            date.setDate(date.getDate() + 30);
            return date.toISOString().slice(0, 10);
        };

        expect((wrapper.find('#due_date').element as HTMLInputElement).value).toBe(expected());
    });

    it('posts to the store route on save for a new invoice', async () => {
        const wrapper = mountForm();

        await wrapper.find('form').trigger('submit');

        expect(formCalls.post.at(-1)).toBe('/sales.invoices.store');
    });

    it('puts to the update route when an invoice id exists', async () => {
        const wrapper = mountForm({
            invoice: { id: 5, customer_id: 1, invoice_date: '2026-09-01', due_date: null, reference: null, notes: null },
            lines: [{ product_id: 1, description: '', quantity: '2', unit_price: '150', discount_amount: '0', tax_rate_id: 1 }],
        });

        expect(wrapper.text()).toContain('Update Draft');

        await wrapper.find('form').trigger('submit');

        expect(formCalls.put.at(-1)).toBe('/sales.invoices.update');
    });
});