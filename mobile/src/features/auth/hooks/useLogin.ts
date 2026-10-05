import { useState } from 'react';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { login } from '@/features/auth/store/authSlice';
import { DEMO_CREDENTIALS, isDemoLoginEnabled } from '@/constants/demoUser';

export function useLogin() {
  const dispatch = useAppDispatch();
  const { status, error } = useAppSelector(state => state.auth);
  //Pre-fill the demo account when it's enabled so you can just tap sign in
  const [email, setEmail] = useState(isDemoLoginEnabled ? DEMO_CREDENTIALS.email:'');
  const [password, setPassword] = useState(isDemoLoginEnabled ? DEMO_CREDENTIALS.password:'');

  const submit = () => {
    if (!email || !password) {
      return;
    }
    dispatch(login({ email, password }));
  };

  return {
    email,
    setEmail,
    password,
    setPassword,
    submit,
    loading: status === 'loading',
    error,
    demoEnable: isDemoLoginEnabled
  };
}
