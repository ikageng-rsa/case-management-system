/**
 * Deliberately hand-rolled rather than Intl.NumberFormat: en-ZA's actual
 * locale convention uses a comma decimal separator and a non-breaking-space
 * thousands separator (confirmed by the test suite catching this), which
 * doesn't match the "R 1,850.00" convention this app's screens expect —
 * and Hermes's ICU data varies by build, so relying on locale formatting
 * for money is a real cross-platform risk. This gives one deterministic
 * result everywhere.
 */
export function formatCurrency(amount: number): string {
  const negative = amount < 0;
  const fixed = Math.abs(amount).toFixed(2);
  const [wholePart, centsPart] = fixed.split('.');

  let grouped = '';
  for (let i = 0; i < wholePart.length; i++) {
    const fromEnd = wholePart.length - i;
    grouped += wholePart[i];
    if (fromEnd > 1 && fromEnd % 3 === 1) {
      grouped += ',';
    }
  }

  return `${negative ? '-' : ''}R ${grouped}.${centsPart}`;
}

export function formatCurrencyCompact(amount: number): string {
  if (Math.abs(amount) >= 1_000_000) {
    return `R ${(amount / 1_000_000).toFixed(1)}m`;
  }
  if (Math.abs(amount) >= 1_000) {
    return `R ${(amount / 1_000).toFixed(1)}k`;
  }
  return formatCurrency(amount);
}
