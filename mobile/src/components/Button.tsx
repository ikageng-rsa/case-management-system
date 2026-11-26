import React from 'react';
import { StyleProp, ViewStyle } from 'react-native';
import { Button as UiButton, ButtonVariant as UiVariant } from '@/ui';

type Variant = 'primary' | 'secondary' | 'danger';

interface ButtonProps {
    label: string;
    onPress: () => void;
    variant?: Variant;
    loading?: boolean;
    disabled?: boolean;
    style?: StyleProp<ViewStyle>;
}

const VARIANTS: Record<Variant, UiVariant> = {
    primary: 'primary',
    secondary: 'outline-secondary',
    danger: 'danger',
};

/** App-level button: same props the screens already use, drawn by the shared UI kit. */
export default function Button({ label, onPress, variant = 'primary', loading = false, disabled = false, style }: ButtonProps) {
    return (
        <UiButton variant={VARIANTS[variant]} onPress={onPress} loading={loading} disabled={disabled} style={style}>
            {label}
        </UiButton>
    );
}
