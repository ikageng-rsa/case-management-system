import { colors } from '@/theme/index';
import { formatDuration } from '@/utils/formatDuration';

export interface DashboardStats {
    openCases: number;
    urgentCases: number;
    overdueDiary: number;
    dueThisWeek: number;
    narrationsThisWeek: number;
    minutesThisWeek: number;
}

export interface Tile {
    label: string;
    value: string | number;
    color: string;
    onPress?: () => void;
}

interface Access {
    showCases: boolean;
    showDiary: boolean;
}

interface Nav {
    navigate: (path: '/cases' | '/diary') => void;
    push: (path: '/diary/overdue') => void;
}

/** A tile is only tappable when the user may open the tab it leads to. */
const whenAllowed = (allowed: boolean, action: () => void) => (allowed ? action : undefined);

export function buildTiles(stats: DashboardStats, access: Access, nav: Nav): Tile[] {
    const { showCases, showDiary } = access;
    return [
        { label: 'Open cases', value: stats.openCases, color: colors.navy, onPress: whenAllowed(showCases, () => nav.navigate('/cases')) },
        { label: 'Urgent cases', value: stats.urgentCases, color: colors.burgundy, onPress: whenAllowed(showCases, () => nav.navigate('/cases')) },
        { label: 'Overdue diary', value: stats.overdueDiary, color: colors.red, onPress: whenAllowed(showDiary, () => nav.push('/diary/overdue')) },
        { label: 'Due this week', value: stats.dueThisWeek, color: colors.gold, onPress: whenAllowed(showDiary, () => nav.navigate('/diary')) },
        { label: 'Entries this week', value: stats.narrationsThisWeek, color: colors.green },
        { label: 'Time this week', value: formatDuration(stats.minutesThisWeek), color: colors.purple },
    ];
}