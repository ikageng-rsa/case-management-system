import React from "react";
import { ActivityIndicator,Pressable,StyleSheet,Text,ViewStyle } from "react-native";
import { colors, radius,spacing,typography } from "@/theme/index";

interface ButtonProps {
    readonly label: string;
    readonly onPress: () => void;
    readonly loading?: boolean;
    readonly disabled?: boolean;
    readonly style?: ViewStyle;
    readonly variant?: 'primary' | 'secondary' | 'danger';
}

export default function Button({
    label,
    onPress,
    variant = 'primary',
    loading = false,
    disabled = false,
    style,
}: ButtonProps) {
    let backgroundColor: string;

    switch (variant) {
        case 'primary':
        backgroundColor = colors.navy;
        break;
        case 'danger':
        backgroundColor = colors.danger;
        break;
        default:
        backgroundColor = colors.surface;
        break;
    }

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
                { backgroundColor, borderColor,  opacity: pressed ? 0.85 : disabled ? 0.5 : 1,},
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