import { createAsyncThunk, createSlice, PayloadAction } from '@reduxjs/toolkit';
import { login as loginRequest, LoginResponse } from '@/api/endpoint/auth.api';
import { setAuthToken, clearAuthToken } from '@/api/client';
import { getApiErrorMessage } from '@/api/errors';
import { User } from '@/models/index';
import { DEMO_TOKEN, DEMO_USER, isDemoLogin } from '@/constants/demoUser';
import { getTwoFactorSettings } from '@/features/auth/services/twoFactorService';
import { clearSessionUser, saveOfflineCredential, saveSessionUser, verifyOfflineLogin } from '@/features/auth/services/offlineAuth';
import { isDeviceOffline, isNetworkError } from '@/offline/connectivity';
import { setTwoFactorPending } from '@/features/auth/services/twoFactorPending';

interface AuthState {
  user: User | null;
  // A user who has entered a correct password but still needs to pass a 2FA
  // challenge before `user` is populated and Stack.Protected lets them into (tabs).
  pendingUser: User | null;
  status: 'idle' | 'loading' | 'authenticated' | 'error' | 'needs-2fa';
  error: string | null;
}

const initialState: AuthState = {
  user: null,
  pendingUser: null,
  status: 'idle',
  error: null,
};

/** Internal marker: skip the network and use the credential saved on this device. */
class OfflineSignIn extends Error {}

interface LoginResult extends LoginResponse {
  twoFactorRequired: boolean;
}

export const login = createAsyncThunk<LoginResult, { email: string; password: string }>(
  'auth/login',
  async ({ email, password }) => {
    let response: LoginResponse;
    let signedInOffline = false;
    // Demo account: authenticate locally, no network call (dev builds / opt-in only).
    if (isDemoLogin(email, password)) {
      await setAuthToken(DEMO_TOKEN);
      response = { token: DEMO_TOKEN, user: DEMO_USER };
    } else {
      // No connection: don't wait for a request to time out — go straight to the saved sign-in.
      const offline = await isDeviceOffline();
      try {
        if (offline) {
          throw new OfflineSignIn();
        }
        response = await loginRequest(email, password);
        await setAuthToken(response.token);
        // Remember this sign-in (hashed password + profile) so the next one can happen offline.
        await saveOfflineCredential({ email, password, user: response.user, token: response.token }).catch(() => {});
      } catch (error) {
        if (!(error instanceof OfflineSignIn) && !isNetworkError(error)) {
          // Surface the backend's own message (e.g. Laravel's 422 "These credentials do not match our records.").
          throw new Error(getApiErrorMessage(error, 'Login failed'));
        }
        const saved = await verifyOfflineLogin(email, password);
        if (!saved.ok) {
          throw new Error(saved.message);
        }
        await setAuthToken(saved.token);
        response = { token: saved.token, user: saved.user };
        signedInOffline = true;
      }
    }
    await saveSessionUser(response.user).catch(() => {});

    const twoFactor = await getTwoFactorSettings(response.user.id);
    if (signedInOffline && twoFactor.method === 'sms') {
      await clearAuthToken();
      throw new Error(
        'Your account uses SMS verification, which needs a connection. Connect to the internet to sign in, or switch to an authenticator app in Security settings.',
      );
    }
    // Until the second factor is passed the token must not restore a session on the next app start.
    await setTwoFactorPending(twoFactor.method !== 'none');
    return { ...response, twoFactorRequired: twoFactor.method !== 'none' };
  },
);

export const logout = createAsyncThunk('auth/logout', async () => {
  await clearAuthToken();
  await clearSessionUser();
  await setTwoFactorPending(false);
});

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setUser(state, action: PayloadAction<User | null>) {
      state.user = action.payload;
      state.status = action.payload ? 'authenticated' : 'idle';
    },
    /** Dispatched once the screen has verified the user's TOTP/SMS code. */
    confirmTwoFactor(state) {
      if (state.pendingUser) {
        state.user = state.pendingUser;
        state.pendingUser = null;
        state.status = 'authenticated';
      }
    },
    cancelTwoFactor(state) {
      state.pendingUser = null;
      state.status = 'idle';
    },
  },
  extraReducers: builder => {
    builder
      .addCase(login.pending, state => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(login.fulfilled, (state, action) => {
        if (action.payload.twoFactorRequired) {
          state.status = 'needs-2fa';
          state.pendingUser = action.payload.user;
        } else {
          state.status = 'authenticated';
          state.user = action.payload.user;
        }
      })
      .addCase(login.rejected, (state, action) => {
        state.status = 'error';
        state.error = action.error.message ?? 'Login failed';
      })
      .addCase(logout.fulfilled, state => {
        state.user = null;
        state.pendingUser = null;
        state.status = 'idle';
      });
  },
});

export const { setUser, confirmTwoFactor, cancelTwoFactor } = authSlice.actions;
export default authSlice.reducer;
