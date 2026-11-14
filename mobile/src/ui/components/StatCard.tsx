import { StyleProp, StyleSheet, Text, View, ViewStyle } from 'react-native';
import { fontFamily, fontSize, space, Theme, Tone, typography, useStyles, useTheme } from '../theme';
import { Card } from './Card';

export interface StatCardProps {
    label: string;
    value: string | number;
    delta?: string;
    tone?: Tone;
    style?: StyleProp<ViewStyle>;
}

export function StatCard({ label, value, delta, tone = 'success', style }: StatCardProps) {
    const theme = useTheme();
    const styles = useStyles(makeStyles);

    return (
        <Card style={[styles.card, style]}>
            <Text style={styles.label}>{label}</Text>

            <View style={styles.row}>
                <Text style={styles.value}>{value}</Text>
                {delta ? <Text style={[styles.delta, { color: theme.tones[tone].bg }]}>{delta}</Text> : null}
            </View>
        </Card>
    );
}

const makeStyles = (t: Theme) =>
    StyleSheet.create({
        card: {
            gap: space[8],
            padding: space[18],
        },
        label: {
            ...typography.label,
            color: t.colors.fgMuted,
        },
        row: {
            flexDirection: 'row',
            alignItems: 'baseline',
            gap: space[8],
        },
        value: {
            ...typography.statValue,
            color: t.colors.fg,
        },
        delta: {
            fontFamily: fontFamily.sansSemibold,
            fontSize: fontSize.base,
        },
    });
