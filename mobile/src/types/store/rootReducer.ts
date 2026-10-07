import { combineReducers } from '@reduxjs/toolkit';
import authReducer from '@/features/auth/store/authSlice';
import casesReducer from '@/features/cases/store/casesSlice';

const rootReducer = combineReducers({
  auth: authReducer,
  cases: casesReducer,
});

export type RootState = ReturnType<typeof rootReducer>;
export default rootReducer;
