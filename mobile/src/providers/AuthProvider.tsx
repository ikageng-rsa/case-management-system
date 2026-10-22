import React, { PropsWithChildren, useEffect, useState } from 'react';
import * as SplashScreen from 'expo-splash-screen';
import { getAuthToken, clearAuthToken } from '@/api/client';
import { fetchCurrentUser } from '@/api/endpoint/auth.api';
import { useAppDispatch } from '@/hooks/useAppDispatch';
import { setUser } from '@/features/auth/store/authSlice';
import { DEMO_TOKEN, DEMO_USER, isDemoLoginEnabled } from '@/constants/demoUser';
import { getSessionUser, saveSessionUser, clearSessionUser } from '@/features/auth/services/offlineAuth';
import { isTwoFactorPending, setTwoFactorPending } from '@/features/auth/services/twoFactorPending';
import { isDeviceOffline } from '@/offline/connectivity';
import { isAxiosError } from 'axios';

// Keep the native splash up until the persisted session has been checked.
SplashScreen.preventAutoHideAsync().catch(() => {});

/**
 * On cold start: if a token was persisted from a previous session, verify it
 * against the backend and hydrate the auth slice. Children (and therefore the
 * router, whose root layout decides between (auth) and (tabs) via
 * Stack.Protected) only mount once that check has finished, so the login
 * screen never flashes for an already-signed-in user.
 */
export default function AuthProvider({ children }: PropsWithChildren) {
  const dispatch = useAppDispatch();
  const [ready, setReady] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const token = await getAuthToken();
        if (!token) {
          return;
        }
        // A demo session has no server-side counterpart, so restore it locally.
        if (isDemoLoginEnabled && !process.env.EXPO_PUBLIC_API_BASE_URL && token === DEMO_TOKEN) {
          dispatch(setUser(DEMO_USER));
          return;
        }
        // The app was closed between "password accepted" and "2FA code accepted": no session.
        if (await isTwoFactorPending()) {
          await clearAuthToken();
          await setTwoFactorPending(false);
          dispatch(setUser(null));
          return;
        }
        // Offline: keep the user signed in with the profile saved at their last online sign-in.
        const restoreOffline = async () => {
          const saved = await getSessionUser();
          dispatch(setUser(saved));
          if (!saved) {
            await clearAuthToken();
          }
        };
        if (await isDeviceOffline()) {
          await restoreOffline();
          return;
        }
        try {
          const user = await fetchCurrentUser();
          await saveSessionUser(user).catch(() => {});
          dispatch(setUser(user));
        } catch (error) {
          if (isAxiosError(error) && error.response?.status === 401) {
            // The server no longer accepts this session.
            await clearAuthToken();
            await clearSessionUser();
            dispatch(setUser(null));
          } else {
            // Server unreachable / timed out: same as offline — don't throw the user out.
            await restoreOffline();
          }
        }
      } finally {
        setReady(true);
        SplashScreen.hideAsync().catch(() => {});
      }
    })();
  }, [dispatch]);

  return ready ? <>{children}</> : null;
}
