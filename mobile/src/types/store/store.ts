import { configureStore } from '@reduxjs/toolkit';
import rootReducer from '@/types/store/rootReducer';

export const store = configureStore({
  reducer: rootReducer,
});

export type AppDispatch = typeof store.dispatch;
