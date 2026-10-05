import React, { PropsWithChildren, useEffect, useState } from 'react';
import * as SplashScreen from 'expo-splash-screen';
import { getAuthToken, clearAuthToken } from '@/api/client';
import { fetchCurrentUser } from '@/api/endpoint/auth.api';
import { useAppDispatch } from '@/hooks/useAppDispatch';
import { setUser } from '@/features/auth/store/authSlice';

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
    void (async () => {
      try {
        const token = await getAuthToken();

        if (!token) {
          return;
        }

        try {
          const user = await fetchCurrentUser();
          dispatch(setUser(user));
        } catch {
          await clearAuthToken();
          dispatch(setUser(null));
        }
      } finally {
        setReady(true);
        SplashScreen.hideAsync().catch(() => {});
      }
    })();
  }, [dispatch]);

  return ready ? <>{children}</> : null;
}