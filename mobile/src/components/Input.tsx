import React from 'react';
import { StyleSheet, Text, TextInput, TextInputProps, View } from 'react-native';
import { colors, spacing, radius, typography } from '@/theme/index';

interface InputProps extends TextInputProps{
    readonly label?: string;
    readonly error?: string;
}

export default function Input({label, error, style, ...rest}: InputProps){
    return(
        <View style={styles.container}>
            <Text style={styles.label}>{label}</Text>
            <TextInput
                placeholderTextColor={colors.textSecondary}
                style={[styles.input, error? styles.inputError : null, style]}
                {...rest}
            />
            {error? <Text style={styles.errorText}>{error}</Text> : null}
        </View>
    );
}

const styles = StyleSheet.create({
    container: {
        marginBottom: spacing.md
    },
    label:{
        ...typography.caption,
        color: colors.textSecondary,
        marginBottom: spacing.xs
    },
    input:{
        borderWidth: 1,
        borderColor: colors.border,
        borderRadius: radius.sm,
        paddingHorizontal: spacing.sm + 2,
        fontSize: 14,
        color: colors.textPrimary,
        backgroundColor: colors.surface
    },
    inputError:{
        borderColor: colors.danger
    },
    errorText:{
        ...typography.caption,
        color: colors.danger,
        marginTop: spacing.xs
    }
})