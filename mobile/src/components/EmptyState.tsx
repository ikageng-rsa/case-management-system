import React from 'react';
import { StyleSheet, Text,View } from 'react-native';
import { colors, spacing, typography} from '@/theme/index';

interface EmptyStateProps{
    title: string;
    message?: string;
}

export default function EmptyState ({title,message}: EmptyStateProps){
    return(
        <View style={styles.container}>
            <Text style={styles.title}>{title}</Text>
            {message ? <Text style={styles.message}>{message}</Text>:null}
        </View>
    );
}

const styles = StyleSheet.create({
    container:{
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: spacing.xl
    },
    title: {
        ...typography.h2,
        color: colors.textPrimary,
        marginBottom: spacing.xs
    },
    message: {
        ...typography.body,
        color: colors.textSecondary,
        textAlign: 'center'
    }
})