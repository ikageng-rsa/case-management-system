import { useCallback, useEffect } from 'react';
import { AppState } from 'react-native';
import { fetchCases } from '@/api/endpoint/cases.api';
import { fetchDiary } from '@/api/endpoint/darzations.api';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { useOnSynced } from '@/hooks/useSyncStatus';
import { cancelDiaryReminders, syncDiaryReminders } from '@/features/darzations/services/notificationScheduler';
import { visibleDarzationsFor } from '@/utils/caseAccess';

/** Keeps the local fallback reminders in step with the (cached) diary of the signed-in user. */
export function useDiaryReminders(): void {
    const user = useAppSelector(state => state.auth.user);

    const refresh = useCallback(async () => {
        if (!user) {
            return;
        }
        try {
            const [diary, cases] = await Promise.all([fetchDiary(), fetchCases()]);
            await syncDiaryReminders(visibleDarzationsFor(diary, cases, user));
        } catch {
            // nothing cached yet — try again next time
        }
    }, [user]);

    useEffect(() => {
        if (!user) {
            cancelDiaryReminders().catch(() => { });
            return;
        }
        refresh();
        const sub = AppState.addEventListener('change', state => {
            if (state === 'active') {
                refresh();
            }
        });
        return () => sub.remove();
    }, [user, refresh]);

    useOnSynced(refresh);
}
