import { createAsyncThunk, createSlice } from '@reduxjs/toolkit';
import { fetchCases } from '@/api/endpoint/cases.api';
import { Case } from '@/models/index';

interface CasesState {
  items: Case[];
  status: 'idle' | 'loading' | 'loaded' | 'error';
  error: string | null;
}

const initialState: CasesState = {
  items: [],
  status: 'idle',
  error: null,
};

export const loadCases = createAsyncThunk(
  'cases/load',
  async (params?: { status?: string; search?: string }) => {
    return fetchCases(params);
  },
);

const casesSlice = createSlice({
  name: 'cases',
  initialState,
  reducers: {},
  extraReducers: builder => {
    builder
      .addCase(loadCases.pending, state => {
        state.status = 'loading';
      })
      .addCase(loadCases.fulfilled, (state, action) => {
        state.status = 'loaded';
        state.items = action.payload;
      })
      .addCase(loadCases.rejected, (state, action) => {
        state.status = 'error';
        state.error = action.error.message ?? 'Failed to load cases';
      });
  },
});

export default casesSlice.reducer;
