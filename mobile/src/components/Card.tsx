import React, { PropsWithChildren } from 'react';
import { StyleProp, StyleSheet, ViewStyle } from 'react-native';
import { Card as UiCard } from '@/ui';
import { spacing } from '@/theme/index';

interface CardProps {
    style?: StyleProp<ViewStyle>;
}

/** Cards stack in lists, so this adds the bottom margin the kit's bare Card leaves out. */
export default function Card({ children, style }: PropsWithChildren<CardProps>) {
    return <UiCard style={[styles.card, style]}>{children}</UiCard>;
}

const styles = StyleSheet.create({
    card: { marginBottom: spacing.md },
});
