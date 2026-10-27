export type NarrationActivityType =
  | 'Call'
  | 'Draft'
  | 'Appearance'
  | 'Travel'
  | 'Research'
  | 'Meeting'
  | 'Other';

export interface Narration {
  id: string;
  caseId: string;
  // The diary (Darzation) item this entry records work against, if any —
  // lets a diary item's full history be pulled up from either direction.
  darzationId?: string;
  authorId: string;
  activityType: NarrationActivityType;
  title?: string;
  description: string;
  detailedNotes?: string;
  timeSpentMinutes: number;
  billable: boolean;
  tariffCode?: string;
  // CaseDocument ids uploaded specifically to this narration (not just the parent case).
  documentIds: string[];
  createdAt: string;
  /** Set only on records created/edited offline that the server hasn't confirmed yet (never sent to the API). */
  syncState?: 'pending' | 'failed';
}
