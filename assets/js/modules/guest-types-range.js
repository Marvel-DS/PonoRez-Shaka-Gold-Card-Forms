export function resolveGuestRange({
    min,
    max,
    fallbackMax,
    hasExplicitMax = false,
    defaultRange = 10,
}) {
    const boundedMin = Number.isFinite(min) ? Math.max(0, Math.floor(min)) : 0;

    const fallbackFloor = Number.isFinite(fallbackMax) ? Math.floor(fallbackMax) : Number.NaN;
    let normalisedFallback = Number.isFinite(fallbackFloor)
        ? Math.max(boundedMin, fallbackFloor)
        : Number.NaN;

    if (!Number.isFinite(normalisedFallback) || normalisedFallback <= boundedMin) {
        normalisedFallback = boundedMin + defaultRange;
    }

    const maxCandidate = Number(max);
    const hasValidMax = Number.isFinite(maxCandidate);
    const flooredMax = hasValidMax ? Math.floor(maxCandidate) : Number.NaN;

    let boundedMax = hasValidMax ? Math.max(boundedMin, flooredMax) : boundedMin;
    const maxBelowMin = hasValidMax && flooredMax < boundedMin;

    if (maxBelowMin) {
        boundedMax = normalisedFallback;
    } else if (!hasExplicitMax && boundedMax === boundedMin) {
        boundedMax = normalisedFallback;
    }

    return {
        min: boundedMin,
        max: boundedMax,
        fallbackMax: Math.max(boundedMax, normalisedFallback),
    };
}

export default resolveGuestRange;
