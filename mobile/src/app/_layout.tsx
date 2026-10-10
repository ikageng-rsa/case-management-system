import React from 'react';
import { Stack } from 'expo-router';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import QueryProvider from '@/providers/QueryProvider';
import ThemeProvider from '@/providers/ThemeProvider';
import AuthProvider from '@/providers/AuthProvider';
import OfflineBanner from '@/components/OfflineBanner';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { useNetInfo } from '@/hooks/useNetInfo';
import { colors } from '@/theme/index';

export { ErrorBoundary } from 'expo-router';

export default function RootLayout() {
  return (
    <QueryProvider>
      <AuthProvider>
        <SafeAreaProvider>
          <ThemeProvider>
            <OfflineBannerHost />
            <RootNavigator />
          </ThemeProvider>
        </SafeAreaProvider>
      </AuthProvider>
    </QueryProvider>
  );
}

/**
 * Only claims the top safe-area inset while the banner is actually visible —
 * otherwise an empty SafeAreaView would push the whole navigator down by the
 * status-bar height and the native headers would add the inset a second time.
 */
function OfflineBannerHost() {
  const { isOnline } = useNetInfo();
  if (isOnline) {
    return null;
  }
  return (
    <SafeAreaView edges={['top']} style={{ flex: 0, backgroundColor: colors.gold }}>
      <OfflineBanner />
    </SafeAreaView>
  );
}

/**
 * Auth gate. Stack.Protected removes the guarded group from the navigator
 * (and redirects away from it) whenever `guard` is false, so signing in/out
 * swaps (auth) <-> (tabs) automatically — no manual navigation reset needed.
 * AuthProvider holds the splash screen until the persisted session has been
 * checked, so there is no login-screen flash on cold start.
 */
function RootNavigator() {
  const isSignedIn = useAppSelector(state => state.auth.user !== null);

  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Protected guard={isSignedIn}>
        <Stack.Screen name="(tabs)" />
      </Stack.Protected>
      <Stack.Protected guard={!isSignedIn}>
        <Stack.Screen name="(auth)" />
      </Stack.Protected>
    </Stack>
  );
}
