import { useState } from 'react';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { login } from '@/features/auth/store/authSlice';

export function useLogin() {
  const dispatch = useAppDispatch();
  const { status, error } = useAppSelector(({state}:any) => state.auth);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');

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
  };
}
