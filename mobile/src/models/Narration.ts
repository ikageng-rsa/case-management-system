export type NarrationActivityType = 'Call' | 'Draft' | 'Appearance' | 'Travel' | 'Other';

export interface Narration {
  id: string;
  caseId: string;
  authorId: string;
  activityType: NarrationActivityType;
  description: string;
  billable: boolean;
  tariffCode?: string;
  createdAt: string;
}
