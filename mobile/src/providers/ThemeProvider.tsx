import React, { createContext, PropsWithChildren, useContext } from 'react';
import { StatusBar } from 'expo-status-bar';
import theme, { AppTheme } from '@/theme/index';

const ThemeContext = createContext<AppTheme>(theme);

/**
 * Provides the "Law" theme via context (on top of the static @theme/index
 * import already used everywhere) so components that need it dynamically —
 * or a future light/dark or per-firm-brand variant — can read it with
 * useAppTheme() instead of threading props. Currently a single fixed theme;
 * swap the value here for a stateful one if per-firm branding is ever needed.
 */
export default function ThemeProvider({ children }: PropsWithChildren) {
  return (
    <ThemeContext.Provider value={theme}>
      <StatusBar style="light" />
      {children}
    </ThemeContext.Provider>
  );
}

export function useAppTheme(): AppTheme {
  return useContext(ThemeContext);
}
