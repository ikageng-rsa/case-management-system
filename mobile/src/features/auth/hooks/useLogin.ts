import { useState } from 'react';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { login } from '@/features/auth/store/authSlice';
import { useRouter } from 'expo-router';
import { getErrorMessage } from '@/utils/ErrorMessage';

export function useLogin() {
  const dispatch = useAppDispatch();
  const router = useRouter();
  const { status, error } = useAppSelector(state => state.auth);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const errorMessage = error ? getErrorMessage(error) : null
  const submit = async () => {
    if (!email || !password) {
      return;
    }
    console.log(`error test: `,error);
    const result = await dispatch(login({ email, password }));
    if (login.fulfilled.match(result) && result.payload.kind === 'challenge') {
      router.push('/two-factor');
    }
  };
  return {
    email,
    setEmail,
    password,
    setPassword,
    submit,
    loading: status === 'loading',
    error: errorMessage,
    status
  };
}
