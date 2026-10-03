import { createAsyncThunk, createSlice, PayloadAction } from '@reduxjs/toolkit';
import { login as loginRequest, LoginResponse } from '@/api/endpoint/auth.api';
import { setAuthToken, clearAuthToken } from '@/api/client';
import { User } from '@/models/index';

interface AuthState {
  user: User | null;
  status: 'idle' | 'loading' | 'authenticated' | 'error';
  error: string | null;
}

const initialState: AuthState = {
  user: null,
  status: 'idle',
  error: null,
};

export const login = createAsyncThunk<LoginResponse, { email: string; password: string }>(
  'auth/login',
  async ({ email, password }: {email: string,password:string}) => {
    const response = await loginRequest(email, password);
    await setAuthToken(response.token);
    return response;
  },
);

export const logout = createAsyncThunk('auth/logout', async () => {
  await clearAuthToken();
});

const authSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setUser(state:any, action: PayloadAction<User | null>) {
      state.user = action.payload;
      state.status = action.payload ? 'authenticated' : 'idle';
    },
  },
  extraReducers: ({builder}:any) => {
    builder
      .addCase(login.pending, ({state}:any) => {
        state.status = 'loading';
        state.error = null;
      })
      .addCase(login.fulfilled, ({state, action}:any) => {
        state.status = 'authenticated';
        state.user = action.payload.user;
      })
      .addCase(login.rejected, ({state, action}:any) => {
        state.status = 'error';
        state.error = action.error.message ?? 'Login failed';
      })
      .addCase(logout.fulfilled, ({state}:any) => {
        state.user = null;
        state.status = 'idle';
      });
  },
});

export const { setUser } = authSlice.actions;
export default authSlice.reducer;
