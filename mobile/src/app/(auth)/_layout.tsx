import React from 'react';
import { Stack } from 'expo-router';
import { stackScreenOptions} from '@/navigation/screenOptions';

export default function AuthLayout() {
  return (
    <Stack screenOptions={stackScreenOptions}>
      <Stack.Screen name="login" options={{headerShown: false}}/>
      <Stack.Screen name="forgot-password" />
      <Stack.Screen name="reset-password" options={{title: 'Reset Password'}} />
    </Stack>
  );
}