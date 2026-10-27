import { formatDuration } from '@/utils/formatDuration';
import { describe, expect, it } from '@jest/globals';

describe('formatDuration', () => {
  it.each([
    [0, '0m'],
    [25, '25m'],
    [60, '1h'],
    [95, '1h 35m'],
    [-5, '0m'],
  ])('%i minutes -> %s', (input, expected) => {
    expect(formatDuration(input)).toBe(expected);
  });
});
