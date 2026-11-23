import { StyleSheet, View, ViewProps } from 'react-native';
import { radius, space, Theme, useStyles } from '../theme';

export interface CardProps extends ViewProps {
    padded?: boolean;
}

export function Card({ padded = true, style, ...props }: CardProps) {
    const styles = useStyles(makeStyles);

    return <View style={[styles.card, padded && styles.padded, style]} {...props} />;
}

const makeStyles = (t: Theme) =>
    StyleSheet.create({
        card: {
            backgroundColor: t.colors.surface,
            borderWidth: 1,
            borderColor: t.colors.line,
            borderRadius: radius.lg,
        },
        padded: {
            padding: space[16],
        },
    });
