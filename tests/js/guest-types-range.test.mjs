import assert from 'node:assert/strict';
import test from 'node:test';
import { resolveGuestRange } from '../../assets/js/modules/guest-types-range.js';

test('uses fallback range when max is below min despite explicit max', () => {
  const result = resolveGuestRange({
    min: 4,
    max: 1,
    fallbackMax: 9,
    hasExplicitMax: true,
    defaultRange: 10,
  });

  assert.equal(result.min, 4);
  assert.equal(result.max, 9);
  assert.equal(result.fallbackMax, 9);
});

test('keeps single value when explicit min equals max', () => {
  const result = resolveGuestRange({
    min: 2,
    max: 2,
    fallbackMax: 8,
    hasExplicitMax: true,
    defaultRange: 10,
  });

  assert.equal(result.min, 2);
  assert.equal(result.max, 2);
  assert.equal(result.fallbackMax, 8);
});
