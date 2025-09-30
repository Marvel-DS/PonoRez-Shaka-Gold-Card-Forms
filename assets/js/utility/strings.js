export function pluralize(word, count) {
    return Number(count) === 1 ? word : `${word}s`;
}

export function titleCase(value) {
    if (typeof value !== 'string') {
        return '';
    }
    return value.replace(/(^|\s)\S/g, (token) => token.toUpperCase());
}

export function safeString(value, fallback = '') {
    return typeof value === 'string' && value.trim() !== '' ? value : fallback;
}
