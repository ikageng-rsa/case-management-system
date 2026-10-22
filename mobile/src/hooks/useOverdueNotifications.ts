import { useEffect, useRef } from 'react';
import { AppState } from 'react-native';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { fetchDiary } from '@/api/endpoint/darzations.api';
import { visibleDarzationsFor } from '@/utils/caseAccess';
import { fetchCases } from '@/api/endpoint/cases.api';
import { notifyOverdueDarzations } from '@/services/notifications/overdueNotifier';

const POLL_INTERVAL_MS = 15 * 60 * 1000; // 15 minutes while the app is open/foregrounded

/** Polls for overdue diary items scoped to the current user and alerts on them (popup + SMS). */
export function useOverdueNotifications(): void {
    const user = useAppSelector(state => state.auth.user);
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        if (!user) {
            return;
        }

        const check = async () => {
            try {
                const [diary, cases] = await Promise.all([fetchDiary(), fetchCases()]);
                const visible = visibleDarzationsFor(diary, cases, user);
                await notifyOverdueDarzations(visible, user);
            } catch {
                // Offline or backend unreachable — silently skip this poll, try again next interval.
            }
        };

        check();
        intervalRef.current = setInterval(check, POLL_INTERVAL_MS);

        const subscription = AppState.addEventListener('change', state => {
            if (state === 'active') {
                check();
            }
        });

        return () => {
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
            }
            subscription.remove();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [user?.id]);
}
