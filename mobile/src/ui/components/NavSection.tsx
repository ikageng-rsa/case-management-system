import { StyleSheet, Text } from 'react-native';
import { inkColors, space, typography } from '../theme';

export interface NavSectionProps {
    children: string;
}

export function NavSection({ children }: NavSectionProps) {
    return (
        <Text style={styles.title} accessibilityRole="header">
            {children}
        </Text>
    );
}

const styles = StyleSheet.create({
    title: {
        ...typography.label,
        color: inkColors.onInkLabel,
        paddingTop: space[12],
        paddingHorizontal: space[12],
        paddingBottom: space[4],
    },
});
