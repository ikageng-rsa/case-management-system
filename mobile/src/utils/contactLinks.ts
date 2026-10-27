/** Turns a South African number (082 123 4567, +27 82 123 4567, 27821234567) into digits with the 27 prefix. */
export function toInternationalDigits(phone: string): string {
  const digits = phone.replace(/[^\d+]/g, '').replace(/^\+/, '');
  if (digits.startsWith('27')) {
    return digits;
  }
  if (digits.startsWith('0')) {
    return `27${digits.slice(1)}`;
  }
  return digits;
}

export const callLink = (phone: string): string => `tel:+${toInternationalDigits(phone)}`;
export const smsLink = (phone: string): string => `sms:+${toInternationalDigits(phone)}`;
export const whatsAppLink = (phone: string): string => `https://wa.me/${toInternationalDigits(phone)}`;
export const emailLink = (email: string): string => `mailto:${email.trim()}`;
