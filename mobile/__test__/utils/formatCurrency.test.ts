import { formatCurrency, formatCurrencyCompact } from '@/utils/formatCurrency';
import { beforeEach, describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';                            

describe('formatCurrency', () => {
  it('formats a whole number with two decimal places and thousands separator', () => {
    expect(formatCurrency(1850)).toBe('R 1,850.00');
  });

  it('formats zero', () => {
    expect(formatCurrency(0)).toBe('R 0.00');
  });

  it('formats a value with cents, rounding to 2 decimal places', () => {
    expect(formatCurrency(299.995)).toBe('R 300.00');
  });

  it('formats a negative amount', () => {
    expect(formatCurrency(-150)).toBe('-R 150.00');
  });
});

describe('formatCurrencyCompact', () => {
  it('leaves small amounts as full currency', () => {
    expect(formatCurrencyCompact(850)).toBe('R 850.00');
  });

  it('compacts thousands to k', () => {
    expect(formatCurrencyCompact(4200)).toBe('R 4.2k');
  });

  it('compacts millions to m', () => {
    expect(formatCurrencyCompact(2_500_000)).toBe('R 2.5m');
  });
});
