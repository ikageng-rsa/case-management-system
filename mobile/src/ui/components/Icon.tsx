import { Feather } from '@expo/vector-icons';
import type { ComponentProps } from 'react';
import type { StyleProp, TextStyle } from 'react-native';
import { useTheme } from '../theme';

export type IconName = ComponentProps<typeof Feather>['name'];

const sizes = { sm: 16, md: 20, lg: 32 };

export interface IconProps {
    name: IconName;
    size?: keyof typeof sizes | number;
    color?: string;
    style?: StyleProp<TextStyle>;
}

export function Icon({ name, size = 'md', color, style }: IconProps) {
    const theme = useTheme();

    return (
        <Feather
            name={name}
            size={typeof size === 'number' ? size : sizes[size]}
            color={color ?? theme.colors.fg}
            style={style}
            accessibilityElementsHidden
            importantForAccessibility="no"
        />
    );
}
