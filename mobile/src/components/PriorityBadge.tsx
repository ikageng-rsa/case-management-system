import React from 'react';
import { StageBadge, StageTone } from '@/ui';

export type Priority = 'Low' | 'Medium' | 'High' | 'Urgent';

const PRIORITY_TONES: Record<Priority, StageTone> = {
    Low: 'success',
    Medium: 'neutral',
    High: 'accent',
    Urgent: 'danger',
};

export default function PriorityBadge({ priority }: { priority: Priority }) {
    return <StageBadge tone={PRIORITY_TONES[priority] ?? 'neutral'}>{priority}</StageBadge>;
}
