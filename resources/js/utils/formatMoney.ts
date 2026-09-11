const formatter = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
});

export function formatMoney(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '0.00';
    }

    const n = Number(value);

    return Number.isNaN(n) ? String(value) : formatter.format(n);
}