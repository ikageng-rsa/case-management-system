import { createAsyncThunk, createSlice, PayloadAction } from '@reduxjs/toolkit';
import { Platform } from 'react-native';
import { hasFeature } from '@/config/features';
import { logoutRequest, signIn, SignInSuccess, verifySignIn } from '@/api/endpoint/auth.api';
import { clearAuthToken, setAuthToken } from '@/api/client';
import { getApiErrorMessage } from '@/api/errors';
import { TwoFactorChallenge, User } from '@/models/index';
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
import { clearExports } from '@/features/reporting/services/exportReport';
import { unregisterPushDevice } from '@/services/notifications/pushRegistration';
import { getErrorMessage } from '@/utils/ErrorMessage';

interface AuthState {
  user: User | null;
  // Password accepted, second factor still to come: the backend has issued a challenge but NO token yet.
  challenge: TwoFactorChallenge | null;
  /** E-mail the code was sent to, shown on the verification screen. */
  pendingEmail: string | null;
  status: 'idle' | 'loading' | 'authenticated' | 'error' | 'needs-2fa' | 'failed';
  error: string | null;
}

const initialState: AuthState = {
  user: null,
  challenge: null,
  pendingEmail: null,
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

type LoginResult =
  | { kind: 'authenticated'; user: User }
  | { kind: 'challenge'; challenge: TwoFactorChallenge; email: string };

const deviceName = () => `LegalCMS ${Platform.OS} ${Platform.Version}`;

export const login = createAsyncThunk<LoginResult, { email: string; password: string }>(
  'auth/login',
  async ({ email, password }) => {
    // No connection: don't wait for a request to time out — go straight to the saved sign-in.
    const offline = await isDeviceOffline();
    try {
      if (offline) {
        throw new OfflineSignIn();
      }
      // The v1 API always answers with an emailed-code challenge; the token only comes after the code.
      const response = await signIn(email, password, deviceName());
      pendingLogin = { email, password };
      return {
        kind: 'challenge',
        email,
        challenge: {
          token: response.challengeId,
          method: 'totp',
          expiresIn: response.expiresIn,
        } as TwoFactorChallenge,
      };
    } catch (error) {
      if (!(error instanceof OfflineSignIn) && !isNetworkError(error)) {
        // Surface the backend's own message (e.g. Laravel's 422 "These credentials do not match our records.").
        throw new Error(getErrorMessage(error));
      }
    }

    const saved = await verifyOfflineLogin(email, password);
    if (!saved.ok) {
      throw new Error(saved.message);
    }
    // The second factor can't be checked without the server. This phone already passed it online, so for
    // accounts that use one we additionally require the phone's own fingerprint/face/PIN.
    if (saved.user.twoFactorMethod && saved.user.twoFactorMethod !== 'none') {
      if (!(await deviceAuthAvailable())) {
        throw new Error('Offline sign-in for accounts with two-factor needs a screen lock or biometrics set up on this phone. Connect to the internet to sign in.');
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
    return { kind: 'authenticated', user: saved.user };
  },
);

async function completeSignIn(success: SignInSuccess, email: string, password: string): Promise<void> {
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
      const success = await verifySignIn(challenge.token, code.trim());
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
      throw new Error(getErrorMessage(error));
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
    if (hasFeature('signOut')) {
      await attempt(logoutRequest);
    }
    if (email) {
      await attempt(() => stripSavedToken(email));
    }
  }
  await attempt(clearExports);
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
      state.pendingEmail = null;
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
          state.pendingEmail = action.payload.email;
        } else {
          state.status = 'authenticated';
          state.user = action.payload.user;
        }
      })
      .addCase(login.rejected, (state, action) => {
          state.status = 'error';
          state.error = getErrorMessage(
              action.payload ?? action.error.message,
          );
      })
      .addCase(completeTwoFactor.fulfilled, (state, action) => {
        state.user = action.payload;
        state.challenge = null;
        state.pendingEmail = null;
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
