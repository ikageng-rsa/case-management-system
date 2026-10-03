import React, {PropsWithChildren, useEffect} from 'react';
import {getAuthToken, clearAuthToken} from '@api/client';
import {fetchCurrentUser} from '@api/endpoints/auth.api';
import {useAppDispatch} from '@hooks/useAppDispatch';
import {setUser} from '@features/auth/store/authSlice';

/**
 * on cold start: if a token was persisted from a previous session, verify it
 * against the backend and hydrate the auth slice so RootNavigator can decide
 * between the Auth stack and the Main tabs without flashing the login screen.
 */

export default function AuthProvider({children}: PropsWithChildren<{}>) {
    const dispatch = useAppDispatch();

    useEffect(() => {
        (async () =>{
            const token = await getAuthToken();
            if(!token){
                return;
            }
            try{
                const user = await fetchCurrentUser();
                dispatch(setUser(user));
            }catch{
                await clearAuthToken();
                dispatch(setUser(null));
            }
        })();
    },[dispatch]);

    return <> {children}</>
}