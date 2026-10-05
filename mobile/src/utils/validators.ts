export function isValidEmail(value: string): boolean {
  const email = value.trim();

  const atIndex = email.indexOf("@"); /**looks at where the @ is found */

  return (
    atIndex > 0 &&
    atIndex === email.lastIndexOf("@") &&
    atIndex < email.length - 1 &&
    email.indexOf(".", atIndex) > atIndex + 1 &&
    !email.includes(" ")
  );
}

/** Accepts 08x/07x local format or +27 international, spaces/dashes ignored. */
export function isValidSAPhoneNumber(value: string): boolean {
  const digits = value.replace(/[\s-]/g, '');
  return /^(\+27\d{9}|0\d{9})$/.test(digits);
}

export function isRequired(value: string | null | undefined): boolean {
  return typeof value === 'string' && value.trim().length > 0;
}

export interface PasswordStrength {
  score: 0 | 1 | 2 | 3 | 4;
  label: 'Very weak' | 'Weak' | 'Fair' | 'Strong' | 'Very strong';
  meetsMinimum: boolean; // firm policy: 8+ chars, upper, lower, number
}

export function checkPasswordStrength(password: string): PasswordStrength {
  let score = 0;
  if (password.length >= 8) {
    score++;
  }
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
    score++;
  }
  if (/\d/.test(password)) {
    score++;
  }
  if (/[^A-Za-z0-9]/.test(password)) {
    score++;
  }

  const labels: PasswordStrength['label'][] = ['Very weak', 'Weak', 'Fair', 'Strong', 'Very strong'];
  return {
    score: score as PasswordStrength['score'],
    label: labels[score],
    meetsMinimum:
      password.length >= 8 && /[a-z]/.test(password) && /[A-Z]/.test(password) && /\d/.test(password),
  };
}

/**
 * File reference validator — mirrors buildFileReference's shape so a
 * manually-typed reference (e.g. searching Cases) can be sanity-checked
 * before hitting the API.
 */
export function isValidFileReference(value: string): boolean {
  return /^[A-Z]{1,4}\/[A-Z]{2,6}\/\d{1,4}\/\d{4}$/i.test(value.trim());
}
