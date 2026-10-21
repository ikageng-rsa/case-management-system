import { configureStore } from "@reduxjs/toolkit";
import rootReducer from "./rootReducer";
import { setActiveUserId } from "@/offline/context";

export const store = configureStore({
    reducer: rootReducer,
});

export type AppDispatch = typeof store.dispatch;

//The offline cache and Outbox are namespaced per user. Keep the active user in step with auth 
//stale synchronously, so a screen that fetches on mount already reads the right user's data.
const syncActiveUser = () => setActiveUserId(store.getState().auth.user?.id ??null);
syncActiveUser();
store.subscribe(syncActiveUser);