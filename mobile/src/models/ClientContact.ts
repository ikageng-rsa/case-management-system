export type ClientContactType = 'Phone' | 'Email' | 'Fax' | 'Physical Address';

/**
 * Mirrors the backend AddClientContact service — a client (ClientProfile)
 * can have multiple contacts, each blind-indexed on `value` (value_hash)
 * the same way ClientProfile.idNumber is. Treat `value` as sensitive: pass
 * it through popiaLogger rather than console.log directly.
 */
export interface ClientContact {
  id: string;
  clientId: string;
  type: ClientContactType;
  value: string;
  isPrimary: boolean;
  createdAt: string;
}
