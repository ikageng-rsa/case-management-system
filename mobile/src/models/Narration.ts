/** The backend's activity code (e.g. "CALL", "TRAVEL") or a free label. */
export type NarrationActivityType = string;

/** How an activity is counted (the backend's `measure`). */
export type ActivityMeasure = 'minutes' | 'page' | 'kilometre' | 'item' | string;

export interface ActivityType {
  id: string;
  code: string;
  name: string;
  measure: ActivityMeasure;
  /** Quantities are entered in multiples of this (e.g. 15 minutes). */
  increment: number;
  requiresCourt: boolean;
}

export interface Narration {
  id: string;
  caseId: string;
  // Not offered by the v1 API; kept so older cached records still load.
  darzationId?: string;
  authorId: string;
  /** Activity code (CALL, TRAVEL…). */
  activityType: NarrationActivityType;
  activityTypeId?: string;
  activityName?: string;
  measure?: ActivityMeasure;
  title?: string;
  /** What was done (the API's `body`). */
  description: string;
  detailedNotes?: string;
  /** Amount in the activity's unit (minutes, pages, kilometres, items). */
  quantity?: number;
  billableUnits?: number;
  /** Minutes, when the activity is timed; 0 otherwise (used for time totals). */
  timeSpentMinutes: number;
  billable: boolean;
  tariffCode?: string;
  // CaseDocument ids uploaded specifically to this narration (not just the parent case).
  documentIds: string[];
  /** When the work happened (ISO). */
  occurredAt?: string;
  createdAt: string;
  /** Set only on records created/edited offline that the server hasn't confirmed yet (never sent to the API). */
  syncState?: 'pending' | 'failed';
}
