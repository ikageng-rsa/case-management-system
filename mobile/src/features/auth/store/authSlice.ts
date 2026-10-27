import { createAsyncThunk, createSlice, PayloadAction } from '@reduxjs/toolkit';
import {
  isChallenge,
  login as loginRequest,
  logoutRequest,
  LoginSuccess,
  verifyTwoFactorLogin,
} from '@/api/endpoint/auth.api';
import { clearAuthToken, setAuthToken } from '@/api/client';
import { getApiErrorMessage } from '@/api/errors';
import { TwoFactorChallenge, User } from '@/models/index';
import { DEMO_TOKEN, DEMO_USER, isDemoLogin } from '@/constants/demoUser';
import {
  clearSessionUser,
  saveOfflineCredential,
  saveSessionUser,
  stripSavedToken,
  verifyOfflineLogin,
} from '@/features/auth/services/offlineAuth';
import { deviceAuthAvailable, requireDeviceAuth } from '@/features/auth/services/deviceAuth';
import { isDeviceOffline, isNetworkError } from '@/offline/connectivity';
import { listOutbox } from '@/offline/outbox';
import { clearOfflineData } from '@/offline/clear';
import { unregisterPushDevice } from '@/services/notifications/pushRegistration';

interface AuthState {
  user: User | null;
  // Password accepted, second factor still to come: the backend has issued a challenge but NO token yet.
  challenge: TwoFactorChallenge | null;
  status: 'idle' | 'loading' | 'authenticated' | 'error' | 'needs-2fa';
  error: string | null;
}

const initialState: AuthState = {
  user: null,
  challenge: null,
  status: 'idle',
  error: null,
};

/** Internal marker: skip the network and use the credential saved on this device. */
class OfflineSignIn extends Error { }

// Kept in memory only while a two-factor challenge is open, so the offline sign-in credential can be saved
// once the code is accepted. Never stored in Redux (which devtools/logging can serialise) or on disk.
let pendingLogin: { email: string; password: string } | null = null;
export function clearPendingLogin(): void {
  pendingLogin = null;
}

const signInOffline = async (email: string, password: string) => {
  const saved = await verifyOfflineLogin(email, password);

  if (!saved.ok) {
    throw new Error(saved.message);
  }

  // Require local device authentication when offline 2FA is enabled.
  const requiresDeviceAuth =
    saved.user.twoFactorMethod &&
    saved.user.twoFactorMethod !== 'none';

  if (requiresDeviceAuth) {
    if (!(await deviceAuthAvailable())) {
      throw new Error(
        'Offline sign-in for accounts with two-factor needs a screen lock or biometrics set up on this phone. Connect to the internet to sign in.',
      );
    }

    if (!(await requireDeviceAuth('Confirm it is you to sign in offline'))) {
      throw new Error('Phone verification was not completed.');
    }
  }

  if (saved.token) {
    await setAuthToken(saved.token);
  } else {
    await clearAuthToken();
  }

  await saveSessionUser(saved.user).catch(() => { });

  return { kind: 'authenticated' as const, user: saved.user };
};

type LoginResult = { kind: 'authenticated'; user: User } | { kind: 'challenge'; challenge: TwoFactorChallenge };

export const login = createAsyncThunk<LoginResult, { email: string; password: string }>(
  'auth/login',

  async ({ email, password }) => {
    // Demo account: authenticate locally, no network call.
    if (isDemoLogin(email, password)) {
      await setAuthToken(DEMO_TOKEN);
      await saveSessionUser(DEMO_USER).catch(() => { });
      return { kind: 'authenticated', user: DEMO_USER };
    }

    const offline = await isDeviceOffline();

    try {
      if (offline) {
        throw new OfflineSignIn();
      }

      const response = await loginRequest(email, password);

      if (isChallenge(response)) {
        pendingLogin = { email, password };

        return {
          kind: 'challenge',
          challenge: {
            token: response.challengeToken,
            method: response.method,
            maskedPhone: response.maskedPhone,
          },
        };
      }

      await completeSignIn(response, email, password);

      return { kind: 'authenticated', user: response.user };
    } catch (error) {
      if (
        !(error instanceof OfflineSignIn) &&
        !isNetworkError(error)
      ) {
        // Preserve the backend's own authentication error message.
        throw new Error(getApiErrorMessage(error, 'Login failed'));
      }
    }

    return signInOffline(email, password);
  },

);

async function completeSignIn(success: LoginSuccess, email: string, password: string): Promise<void> {
  await setAuthToken(success.token);
  await saveSessionUser(success.user).catch(() => { });
  // Remember this sign-in (hashed password + profile) so the next one can happen offline.
  await saveOfflineCredential({ email, password, user: success.user, token: success.token }).catch(() => { });
}

/** Second step: the backend checks the code and only then issues the session token. */
export const completeTwoFactor = createAsyncThunk<User, { code: string }, { state: { auth: AuthState } }>(
  'auth/completeTwoFactor',
  async ({ code }, { getState }) => {
    const challenge = getState().auth.challenge;
    if (!challenge) {
      throw new Error('Your verification session has expired. Sign in again.');
    }
    try {
      const success = await verifyTwoFactorLogin(challenge.token, code.trim());
      const creds = pendingLogin;
      pendingLogin = null;
      if (creds) {
        await completeSignIn(success, creds.email, creds.password);
      } else {
        await setAuthToken(success.token);
        await saveSessionUser(success.user).catch(() => { });
      }
      return success.user;
    } catch (error) {
      throw new Error(getApiErrorMessage(error, 'Verification failed'));
    }
  },
);

/** Sign-out must always finish, whatever a best-effort step does. */
async function attempt(step: () => unknown): Promise<void> {
  try {
    await step();
  } catch {
    // best effort
  }
}

export const logout = createAsyncThunk<void, void, { state: { auth: AuthState } }>('auth/logout', async (_, { getState }) => {
  const email = getState().auth.user?.email;
  const unsynced = (await listOutbox()).length;

  // Stop pushing this person's alerts to a phone they no longer use.
  await attempt(unregisterPushDevice);
  // Revoke the session on the server, unless offline work still needs this token to sync later.
  if (unsynced === 0) {
    await attempt(logoutRequest);
    if (email) {
      await attempt(() => stripSavedToken(email));
    }
  }
  await clearAuthToken();
  await clearSessionUser();
  clearPendingLogin();
  if (process.env.EXPO_PUBLIC_WIPE_CACHE_ON_LOGOUT === 'true' && unsynced === 0) {
    await attempt(clearOfflineData);
  }
});

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setUser(state, action: PayloadAction<User | null>) {
      state.user = action.payload;
      state.status = action.payload ? 'authenticated' : 'idle';
    },
    /** The server stopped accepting the session token (expired, revoked, password reset elsewhere). */
    sessionExpired(state) {
      state.user = null;
      state.challenge = null;
      state.status = 'idle';
      state.error = 'Your session has ended. Please sign in again.';
    },
    cancelTwoFactor(state) {
      state.challenge = null;
      state.status = 'idle';
      pendingLogin = null;
    },
  },
  extraReducers: builder => {
    builder
      .addCase(login.pending, state => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(login.fulfilled, (state, action) => {
        if (action.payload.kind === 'challenge') {
          state.status = 'needs-2fa';
          state.challenge = action.payload.challenge;
        } else {
          state.status = 'authenticated';
          state.user = action.payload.user;
        }
      })
      .addCase(login.rejected, (state, action) => {
        state.status = 'error';
        state.error = action.error.message ?? 'Login failed';
      })
      .addCase(completeTwoFactor.fulfilled, (state, action) => {
        state.user = action.payload;
        state.challenge = null;
        state.status = 'authenticated';
      })
      .addCase(logout.fulfilled, state => {
        state.user = null;
        state.challenge = null;
        state.status = 'idle';
        state.error = null;
      });
  },
});

export const { setUser, sessionExpired, cancelTwoFactor } = authSlice.actions;
export default authSlice.reducer;
