import React from "react";
import { ActivityIndicator,Pressable,StyleSheet,Text,ViewStyle } from "react-native";
import { colors, radius,spacing,typography } from "@theme/index";

interface ButtonProps {
    label: string;
    onPress: () => void;
    loading?: boolean;
    disabled?: boolean;
    style?: ViewStyle;
    variant?: 'primary' | 'secondary' | 'danger';
}

export default function Button({
    label,
    onPress,
    variant = 'primary',
    loading = false,
    disabled = false,
    style,
}: ButtonProps) {
    const backgroundColor =
        variant === 'primary' ? colors.nevy : 
        variant ==='danger' ? colors.danger : colors.surface;
    const textColor = variant === 'secondary' ? colors.navy : colors.white;
    const borderColor = variant === 'secondary' ? colors.navy : 'transparent';

    return(
        <Pressable
            onPress={onPress}
            disabled={disabled || loading}
            accessibilityRole="button"
            accessibilityState={{disabled: disabled || loading}}
            style ={ ({pressed}) =>[
                styles.base,
                { backgroundColor, borderColor, opacity: pressed ? 0.85 : disabled ? 0.5 : 1 },
                style,
            ]}
            >
                {
                    loading ? (
                        <ActivityIndicator color={textColor}/>
                    ) : (
                        <Text style={[styles.label, {color: textColor}]}>{label}</Text>
                    )
                }
            </Pressable>
    );
}

const styles = StyleSheet.create({
    base:{
        paddingVertical: spacing.md,
        paddingHorizontal: spacing.lg,
        borderRadius: radius.md,
        borderWidth: 1,
        alignItems: 'center',
        justifyContent: 'center'
    },
    label:{
        ...typography.body,
        fontWeight: '600'
    }
})