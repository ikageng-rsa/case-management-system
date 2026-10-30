import { Case, Darzation, Narration, User } from '@/models/index';
import { formatDuration } from '@/utils/formatDuration';

export type ReportKind = 'caseLoad' | 'diaryStatus' | 'overdue' | 'time';
export type TimePeriod = 'week' | 'month' | 'all';

export interface ReportTable {
    title: string;
    subtitle: string;
    columns: string[];
    rows: string[][];
}

export const REPORT_LABELS: Record<ReportKind, string> = {
    caseLoad: 'Case load',
    diaryStatus: 'Diary status',
    overdue: 'Overdue items',
    time: 'Time & billing',
};

export const PERIOD_LABELS: Record<TimePeriod, string> = { week: 'Last 7 days', month: 'Last 30 days', all: 'All time' };

const nameOf = (users: User[], id?: string): string => (id ? users.find(u => u.id === id)?.fullName ?? '' : '');
const refOf = (cases: Case[], id: string): Case | undefined => cases.find(c => c.id === id);
const day = (value?: string): string => (value ? value.slice(0, 10) : '');

export interface ReportInput {
    cases: Case[];
    diary: Darzation[];
    narrations: Narration[];
    users: User[];
    generatedBy: string;
    period?: TimePeriod;
    now?: Date;
}

const stamp = (now: Date, who: string) => `Generated ${now.toISOString().slice(0, 16).replace('T', ' ')} UTC by ${who}`;

export function buildReport(kind: ReportKind, input: ReportInput): ReportTable {
    const now = input.now ?? new Date();
    const { cases, diary, narrations, users } = input;

    if (kind === 'caseLoad') {
        const rows = [...cases]
            .sort((a, b) => a.fileReference.localeCompare(b.fileReference))
            .map(c => {
                const items = diary.filter(d => d.caseId === c.id && d.status !== 'Done');
                return [
                    c.fileReference,
                    c.clientName,
                    c.matterType,
                    c.status,
                    c.priority,
                    nameOf(users, c.leadAttorneyId),
                    day(c.openedDate),
                    day(c.nextCourtDate),
                    String(items.length),
                    String(items.filter(d => d.overdue).length),
                ];
            });
        return {
            title: 'Case load',
            subtitle: `${rows.length} case${rows.length === 1 ? '' : 's'} · ${stamp(now, input.generatedBy)}`,
            columns: ['File ref', 'Client', 'Matter', 'Status', 'Priority', 'Lead attorney', 'Opened', 'Next court date', 'Open diary items', 'Overdue'],
            rows,
        };
    }

    if (kind === 'diaryStatus' || kind === 'overdue') {
        const list = (kind === 'overdue' ? diary.filter(d => d.overdue) : diary)
            .slice()
            .sort((a, b) => (a.dueDate || a.nextActionDate).localeCompare(b.dueDate || b.nextActionDate));
        const rows = list.map(d => [
            d.title || d.nextAction,
            refOf(cases, d.caseId)?.fileReference ?? '',
            refOf(cases, d.caseId)?.clientName ?? '',
            d.status,
            d.priority,
            day(d.dueDate || d.nextActionDate),
            d.overdue ? 'Yes' : 'No',
            `${d.steps?.filter(s => s.done).length ?? 0}/${d.steps?.length ?? 0}`,
            (d.assignedUserIds ?? []).map(id => nameOf(users, id)).filter(Boolean).join('; '),
        ]);
        return {
            title: kind === 'overdue' ? 'Overdue diary items' : 'Diary status',
            subtitle: `${rows.length} item${rows.length === 1 ? '' : 's'} · ${stamp(now, input.generatedBy)}`,
            columns: ['Item', 'File ref', 'Client', 'Status', 'Priority', 'Due', 'Overdue', 'Steps done', 'Assigned to'],
            rows,
        };
    }

    const days = input.period === 'week' ? 7 : input.period === 'month' ? 30 : null;
    const since = days ? now.getTime() - days * 86_400_000 : -Infinity;
    const inRange = narrations
        .filter(n => new Date(n.createdAt).getTime() >= since)
        .sort((a, b) => b.createdAt.localeCompare(a.createdAt));
    const total = inRange.reduce((sum, n) => sum + (n.timeSpentMinutes ?? 0), 0);
    const billable = inRange.filter(n => n.billable).reduce((sum, n) => sum + (n.timeSpentMinutes ?? 0), 0);
    return {
        title: `Time & billing (${PERIOD_LABELS[input.period ?? 'all']})`,
        subtitle: `${inRange.length} entr${inRange.length === 1 ? 'y' : 'ies'} · total ${formatDuration(total)} · billable ${formatDuration(billable)} · ${stamp(now, input.generatedBy)}`,
        columns: ['Date', 'File ref', 'Client', 'Activity', 'Summary', 'Minutes', 'Billable', 'Tariff', 'By'],
        rows: inRange.map(n => [
            day(n.createdAt),
            refOf(cases, n.caseId)?.fileReference ?? '',
            refOf(cases, n.caseId)?.clientName ?? '',
            n.activityType,
            n.title || n.description,
            String(n.timeSpentMinutes ?? 0),
            n.billable ? 'Yes' : 'No',
            n.tariffCode ?? '',
            nameOf(users, n.authorId),
        ]),
    };
}

/**
 * A cell that starts with = + - @ (or a tab/CR) is run as a formula by Excel and Sheets. Client-supplied text
 * ends up in these files, so such cells are prefixed with an apostrophe (OWASP "CSV injection").
 */
function csvCell(value: string): string {
    const safe = /^[=+\-@\t\r]/.test(value) ? `'${value}` : value;
    return /[",\r\n]/.test(safe) ? `"${safe.replace(/"/g, '""')}"` : safe;
}

export function toCsv(table: ReportTable): string {
    const lines = [[table.title], [table.subtitle], [], table.columns, ...table.rows].map(row => row.map(csvCell).join(','));
    // BOM so Excel opens accented South African names correctly.
    return `﻿${lines.join('\r\n')}\r\n`;
}

const esc = (value: string): string =>
    value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

export function toHtml(table: ReportTable): string {
    const head = table.columns.map(c => `<th>${esc(c)}</th>`).join('');
    const body = table.rows.map(r => `<tr>${r.map(c => `<td>${esc(c)}</td>`).join('')}</tr>`).join('');
    return `<!doctype html><html><head><meta charset="utf-8"><style>
  body{font-family:Helvetica,Arial,sans-serif;color:#1a1a1a;padding:16px}
  h1{font-size:18px;margin:0 0 4px;color:#14284b}
  p{font-size:10px;color:#555;margin:0 0 12px}
  table{border-collapse:collapse;width:100%;font-size:9px}
  th{background:#14284b;color:#fff;text-align:left;padding:4px}
  td{border-bottom:1px solid #ddd;padding:4px;vertical-align:top}
  .conf{font-size:9px;color:#a00;margin-top:12px}
  </style></head><body><h1>${esc(table.title)}</h1><p>${esc(table.subtitle)}</p>
  <table><thead><tr>${head}</tr></thead><tbody>${body || `<tr><td colspan="${table.columns.length}">Nothing to report.</td></tr>`}</tbody></table>
  <div class="conf">Confidential — contains client information protected under POPIA. Do not forward outside the firm.</div>
  </body></html>`;
}

export function exportFileName(kind: ReportKind, ext: 'csv' | 'pdf', now = new Date()): string {
    return `legalcms-${kind}-${now.toISOString().slice(0, 10)}.${ext}`;
}
