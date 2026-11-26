import React from 'react';
import { StageBadge } from '@/ui';

/** Marks a record that was saved on this device but hasn't reached the server yet. */
export default function SyncBadge({ state }: { state?: 'pending' | 'failed' }) {
    if (!state) {
        return null;
    }
    const failed = state === 'failed';
    return (
        <StageBadge
            tone={failed ? 'danger' : 'accent'}
            accessibilityLabel={failed ? 'Could not sync' : 'Waiting to sync'}
            testID="sync-badge"
        >
            {failed ? 'Sync failed' : 'Saved on device · waiting to sync'}
        </StageBadge>
    );
}
