const formatterCache = new Map();

function getCurrencyFormatter(currency, locale = 'en-US') {
    const key = `${locale}-${currency}`;
    if (formatterCache.has(key)) {
        return formatterCache.get(key);
    }

    const formatter = new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        currencyDisplay: 'symbol',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    formatterCache.set(key, formatter);
    return formatter;
}

export function formatCurrency(amount, { currency = 'USD', locale = 'en-US' } = {}) {
    const value = Number(amount);
    if (!Number.isFinite(value)) {
        return '--';
    }

    try {
        return getCurrencyFormatter(currency, locale).format(value);
    } catch (error) {
        console.error('Currency formatting error', error);
        return value.toFixed(2);
    }
}

export function formatNumber(value, locales = 'en-US') {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) {
        return '--';
    }

    return new Intl.NumberFormat(locales).format(numeric);
}

export function formatDateIso(date) {
    if (!date) {
        return '--';
    }

    try {
        const instance = typeof date === 'string' ? new Date(`${date}T00:00:00`) : new Date(date);
        return instance.toISOString().slice(0, 10);
    } catch (error) {
        console.error('Date formatting error', error);
        return '--';
    }
}

export function formatDateLong(date, locale = 'en-US') {
    if (!date) {
        return '--';
    }

    try {
        const instance = typeof date === 'string' ? new Date(`${date}T00:00:00`) : new Date(date);
        return new Intl.DateTimeFormat(locale, {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        }).format(instance);
    } catch (error) {
        console.error('Date formatting error', error);
        return String(date);
    }
}
