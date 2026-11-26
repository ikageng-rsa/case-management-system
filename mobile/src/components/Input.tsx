import React from 'react';
import { Input as UiInput, InputProps as UiInputProps } from '@/ui';

interface InputProps extends Omit<UiInputProps, 'label' | 'error'> {
    label: string;
    error?: string;
}

export default function Input({ label, error, ...rest }: InputProps) {
    return (
        <UiInput
            label={label}
            error={error}
            // Secrets never go through autocorrect/predictive text (which can learn and leak them).
            {...(rest.secureTextEntry ? { autoCorrect: false, spellCheck: false, autoCapitalize: 'none' as const } : {})}
            {...rest}
        />
    );
}
