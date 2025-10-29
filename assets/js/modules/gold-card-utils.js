export function normaliseGoldCardEntries(raw) {
    if (typeof raw !== 'string') {
        return [];
    }

    return raw
        .replace(/[\r\n]+/g, ',')
        .split(/\s*[;,]\s*/)
        .map((value) => value.trim())
        .filter((value) => value !== '');
}

export function buildGoldCardSignature(entries) {
    if (!Array.isArray(entries) || entries.length === 0) {
        return '';
    }

    return entries.join(',');
}
