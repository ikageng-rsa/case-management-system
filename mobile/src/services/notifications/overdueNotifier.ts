/**
 * Checks a user's visible diary (Darzation) items for anything overdue and, for
 * each one not already alerted on, fires BOTH an in-app/device popup
 * (expo-notifications, always free) and an SMS (only if the user has a phone
 * number on file and an SMS provider is configured — see smsService.ts).
 *
 * Re-notifying the same item on every poll is avoided with a small on-device
 * timestamp map (AsyncStorage) rather than a backend field, since the backend
 * schema is out of scope for this change.
 */
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Darzation, User } from '@/models/index';
import { computeOverdue } from '@/models/Darzation';
import { showPopupNotification } from './localNotifications';
import { sendSms } from './smsService';
import { logNotification } from './notificationLog';

const RENOTIFY_AFTER_MS = 24 * 60 * 60 * 1000; // at most once a day per item
const LAST_NOTIFIED_KEY = 'legalcms_overdue_last_notified';

async function getLastNotifiedMap(): Promise<Record<string, number>> {
    const raw = await AsyncStorage.getItem(LAST_NOTIFIED_KEY);
    return raw ? (JSON.parse(raw) as Record<string, number>) : {};
}

async function markNotified(darzationId: string, map: Record<string, number>): Promise<void> {
    map[darzationId] = Date.now();
    await AsyncStorage.setItem(LAST_NOTIFIED_KEY, JSON.stringify(map));
}

export interface OverdueNotifyResult {
    notified: Darzation[];
}

export async function notifyOverdueDarzations(
    items: Darzation[],
    user: Pick<User, 'id' | 'fullName' | 'phoneNumber'> | null | undefined,
): Promise<OverdueNotifyResult> {
    const notified: Darzation[] = [];
    if (!user) {
        return { notified };
    }

    const lastNotified = await getLastNotifiedMap();
    const overdue = items.filter(d => {
        if (!computeOverdue(d.dueDate || d.nextActionDate, d.status)) {
            return false;
        }
        const last = lastNotified[d.id];
        return !last || Date.now() - last > RENOTIFY_AFTER_MS;
    });

    for (const item of overdue) {
        const title = 'Overdue diary item';
        const dueDate = item.dueDate || item.nextActionDate;
        const body = `${item.title || item.nextAction} was due ${new Date(dueDate).toLocaleDateString()}`;

        await showPopupNotification({ title, body, data: { darzationId: item.id, caseId: item.caseId } });
        await logNotification({
            title,
            body,
            channel: 'in-app',
            reason: 'overdue-darzation',
            relatedCaseId: item.caseId,
            relatedDarzationId: item.id,
        });

        if (user.phoneNumber) {
            const sms = await sendSms(user.phoneNumber, `LegalCMS: ${body}`);
            await logNotification({
                title: 'Overdue SMS',
                body: sms.ok ? `Sent to ${user.phoneNumber}` : `Failed: ${sms.error}`,
                channel: 'sms',
                reason: 'overdue-darzation',
                relatedCaseId: item.caseId,
                relatedDarzationId: item.id,
            });
        }

        await markNotified(item.id, lastNotified);
        notified.push(item);
    }

    return { notified };
}
