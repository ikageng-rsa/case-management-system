/** Reminder choices for a diary entry. The API accepts 5 to 43 200 minutes before the due time, or none. */
export interface ReminderOption {
    value: string;
    label: string;
    /** null = no reminder. */
    minutes: number | null;
}

export const REMINDER_OPTIONS: ReminderOption[] = [
    { value: 'none', label: 'No reminder', minutes: null },
    { value: '15', label: '15 minutes before', minutes: 15 },
    { value: '60', label: '1 hour before', minutes: 60 },
    { value: '1440', label: '1 day before', minutes: 1440 },
    { value: '10080', label: '1 week before', minutes: 10_080 },
];

/** The option for a stored value; a value the list does not know (set on the web) has no chip selected. */
export function reminderValueFor(minutes: number | null | undefined): string[] {
    if (minutes === null || minutes === undefined) {
        return ['none'];
    }
    const found = REMINDER_OPTIONS.find(o => o.minutes === minutes);
    return found ? [found.value] : [];
}
