import { configureStore } from '@reduxjs/toolkit';
import rootReducer from '@/store/rootReducer';
import { setActiveUserId } from '@/offline/context';
import { setUnauthorizedHandler } from '@/api/client';
import { sessionExpired } from '@/features/auth/store/authSlice';

export const store = configureStore({
    reducer: rootReducer,
});

export type AppDispatch = typeof store.dispatch;

// The offline cache and outbox are namespaced per user. Keep the active user in step with the auth
// state synchronously, so a screen that fetches on mount already reads the right user's data.
const syncActiveUser = () => setActiveUserId(store.getState().auth.user?.id ?? null);
syncActiveUser();
store.subscribe(syncActiveUser);

// A 401 (revoked/expired token) signs the user out; their unsent offline changes stay on the device for next time.
setUnauthorizedHandler(() => {
    if (store.getState().auth.user) {
        store.dispatch(sessionExpired());
    }
});
