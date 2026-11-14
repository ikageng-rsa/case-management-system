import { ActivityIndicator, Pressable, StyleProp, StyleSheet, Text, ViewStyle } from 'react-native';
import { fontFamily, fontSize, radius, space, Theme, Tone, useTheme } from '../theme';
import { Icon, IconName } from './Icon';

export type ButtonVariant = Tone | `outline-${Tone}` | 'link';

export interface ButtonProps {
  children?: string;
  variant?: ButtonVariant;
  size?: 'sm' | 'lg';
  icon?: IconName;
  loading?: boolean;
  disabled?: boolean;
  onPress?: () => void;
  accessibilityLabel?: string;
  style?: StyleProp<ViewStyle>;
}

const heights = { sm: 30, md: 38, lg: 46 };

export function Button({
  children,
  variant = 'primary',
  size,
  icon,
  loading = false,
  disabled = false,
  onPress,
  accessibilityLabel,
  style,
}: ButtonProps) {
  const theme = useTheme();
  const inactive = disabled || loading;
  const iconOnly = icon !== undefined && !children;

  return (
    <Pressable
      onPress={onPress}
      disabled={inactive}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? children}
      accessibilityState={{ disabled: inactive, busy: loading }}
      style={({ pressed }) => {
        const paint = paintFor(theme, variant, pressed);

        return [
          styles.base,
          size && styles[size],
          variant === 'link' && styles.link,
          iconOnly && { paddingHorizontal: 0, width: heights[size ?? 'md'] },
          { backgroundColor: paint.background, borderColor: paint.border },
          inactive && styles.disabled,
          style,
        ];
      }}
    >
      {({ pressed }) => {
        const { foreground } = paintFor(theme, variant, pressed);

        if (loading) {
          return <ActivityIndicator size="small" color={foreground} />;
        }

        return (
          <>
            {icon ? <Icon name={icon} size={size === 'lg' ? 20 : 16} color={foreground} /> : null}
            {children ? (
              <Text style={[styles.label, size === 'sm' && styles.labelSm, { color: foreground }]}>{children}</Text>
            ) : null}
          </>
        );
      }}
    </Pressable>
  );
}

function paintFor(theme: Theme, variant: ButtonVariant, pressed: boolean) {
  if (variant === 'link') {
    const foreground = pressed ? theme.colors.linkHover : theme.colors.link;

    return { background: 'transparent', border: 'transparent', foreground };
  }

  if (variant.startsWith('outline-')) {
    const tone = theme.tones[variant.slice('outline-'.length) as Tone];

    return pressed
      ? { background: tone.bg, border: tone.bg, foreground: tone.fg }
      : { background: 'transparent', border: tone.bg, foreground: tone.bg };
  }

  const tone = theme.tones[variant as Tone];
  const background = pressed ? tone.pressed : tone.bg;

  return { background, border: background, foreground: tone.fg };
}

const styles = StyleSheet.create({
  base: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: space[6],
    minHeight: heights.md,
    paddingVertical: space[8],
    paddingHorizontal: space[16],
    borderWidth: 1,
    borderRadius: radius.md,
  },
  sm: {
    minHeight: heights.sm,
    paddingVertical: space[4],
    paddingHorizontal: space[10],
  },
  lg: {
    minHeight: heights.lg,
    paddingVertical: space[12],
    paddingHorizontal: space[20],
  },
  link: {
    paddingHorizontal: 0,
  },
  disabled: {
    opacity: 0.65,
  },
  label: {
    fontFamily: fontFamily.sansMedium,
    fontSize: fontSize.lg,
    lineHeight: 18,
  },
  labelSm: {
    fontSize: fontSize.base,
  },
});
