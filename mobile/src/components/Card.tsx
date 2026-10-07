import React, { PropsWithChildren} from 'react';
import { StyleSheet,View,ViewStyle } from 'react-native';
import {colors,radius,spacing} from '@/theme/index';

interface CardProps{
    style?: ViewStyle
}

export default function Card({children,style}: PropsWithChildren<CardProps>){
    return <View style={[styles.card,style]}>{children}</View>;
}

const styles = StyleSheet.create({
    card: {
        backgroundColor: colors.surface,
        borderRadius: radius.md,
        borderWidth: 1,
        borderColor: colors.border,
        padding: spacing.md,
        marginBottom: spacing.md
    }
})