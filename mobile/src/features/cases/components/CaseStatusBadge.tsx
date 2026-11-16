import React from 'react';
import { StageBadge, StageTone } from '@/ui';
import { CaseStatus } from '@/models/index';

const STATUS_TONES: Record<CaseStatus, StageTone> = {
  Open: 'success',
  'In Progress': 'accent',
  'On Hold': 'neutral',
  Closed: 'info',
  Archived: 'neutral',
};

export default function CaseStatusBadge({ status }: { status: CaseStatus }) {
  return <StageBadge tone={STATUS_TONES[status] ?? 'neutral'}>{status}</StageBadge>;
}
