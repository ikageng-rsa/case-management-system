import React from 'react';
import { Stack } from 'expo-router';
import { stackScreenOptions} from '@/navigation/screenOptions';

export default function AuthLayout() {
  return (
    <Stack screenOptions={stackScreenOptions}>
      <Stack.Screen name="login" options={{headerShown: false}}/>
      <Stack.Screen name="forgot-password" options={{title: 'Reset Password'}}/>
      <Stack.Screen name="verify-reset-code" options={{title: 'Verify Code'}}/>
      <Stack.Screen name="set-new-password" options={{title: 'New Password'}}/>
      <Stack.Screen name="" options={{title: 'Verify It\'s You', headerBackVisible: false}}/>
    </Stack>
  );
}