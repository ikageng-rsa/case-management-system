export type PopiaConsentType = 'DataProcessing' | 'Marketing' | 'ThirdPartySharing' | 'DocumentRetention';

/** Mirrors the backend RecordPopiaConsent service. */
export interface PopiaConsent {
  id: string;
  clientId: string;
  consentType: PopiaConsentType;
  granted: boolean;
  consentVersion: string; // ties the record to the exact policy text version shown
  recordedById: string; // staff member who captured consent
  recordedAt: string; // ISO date
}
