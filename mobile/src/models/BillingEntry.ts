export interface BillingEntry {
  id: string;
  caseId: string;
  narrationId?: string;
  tariffScale: string;
  amount: number;
  billed: boolean;
  billedAt?: string;
}
