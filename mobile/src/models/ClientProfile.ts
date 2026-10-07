/**
 * Mirrors the backend RegisterClient service, which stores a blind-index
 * hash of idNumber (id_number_hash) for searchable encryption rather than
 * the raw value. The mobile app still submits/displays the raw idNumber
 * where the UI needs it (e.g. a client intake form) — just never log it
 * un-redacted; route any debug output for this type through popiaLogger
 * (@utils/popiaLogger), which already redacts it by key name.
 */
export interface ClientProfile {
  id: string;
  fullName: string;
  idNumber?: string;
  contactNumber: string;
  email: string;
  ficaVerified: boolean;
  riskRating: 'Low' | 'Medium' | 'High';
}
