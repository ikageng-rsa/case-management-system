//the backend activity code (e.g "CALL","TRAVEL") or a free label
export type NarrationActivityType = string;

//How an activity is counted 
export type ActivityMeasure = 'minutes' | 'page' | 'kilometre' | 'item' | string;

export interface ActivityType {
  id: string;
  code: string;
  name: string;
  measure: ActivityMeasure;
  //Quantities are entered in multiples of this (e.g 15 minutes)
  increment: number;
  requiresCourt: boolean;
}

export interface Narration {
  id: string;
  caseId: string;
  // The diary (Darzation) item this entry records work against, if any —
  // lets a diary item's full history be pulled up from either direction.
  darzationId?: string;
  authorId: string;
  activityType: NarrationActivityType;
  activityTypeIs?: string;
  activityName?: string;
  measure?: ActivityMeasure;
  title?: string;
  description: string;
  detailedNotes?: string;
  //Amount in the activity's unit (minutes, pages, kilometres, item)
  quantity?: number;
  billableUnits?: number;
  /** Minutes, when the activity is timed; 0 otherwise (used for time totals). */

  timeSpentMinutes: number;
  billable: boolean;
  tariffCode?: string;
  // CaseDocument ids uploaded specifically to this narration (not just the parent case).
  documentIds: string[];

  //when the work happened (ISO)
  occurredAt?: string;
  createdAt: string;
  /** Set only on records created/edited offline that the server hasn't confirmed yet (never sent to the API). */
  syncState?: 'pending' | 'failed';
}
