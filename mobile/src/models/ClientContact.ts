export type ClientContactKind = 'email' | 'mobile' | 'phone' | 'physical-address' | string;

/**
 * A contact detail of a client as the API returns it: only a masked copy ("038 ··· 1499").
 * The real value is fetched on demand with the reveal call (see api/endpoints/clients.api.ts) and is never stored.
 */
export interface ClientContact {
  id: string;
  kind: ClientContactKind;
  valueMasked: string;
  isPrimary: boolean;
}
