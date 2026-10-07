import { Platform, ViewStyle } from 'react-native';

/**
 * Android uses `elevation`; iOS needs the shadow* quartet. Centralising this
 * means Card/modal/FAB components pick a named level instead of hand-rolling
 * shadow values that drift out of sync across screens.
 */
function level(elevation: number, iosOpacity: number, iosRadius: number): ViewStyle {
  return Platform.select({
    android: { elevation },
    ios: {
      shadowColor: '#000',
      shadowOffset: { width: 0, height: elevation / 2 },
      shadowOpacity: iosOpacity,
      shadowRadius: iosRadius,
    },
    default: {},
  }) as ViewStyle;
}

export const shadows = {
  none: {},
  card: level(2, 0.08, 4),
  raised: level(6, 0.12, 8),
  modal: level(12, 0.18, 16),
};
