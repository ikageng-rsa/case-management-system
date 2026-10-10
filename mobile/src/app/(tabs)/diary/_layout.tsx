import React from 'react';
import { Stack } from 'expo-router';
import { stackScreenOptions } from '@/navigation/screenOptions';

export default function DiaryLayout() {
  return (
    <Stack screenOptions={stackScreenOptions}>
      <Stack.Screen name="index" options={{ headerShown: false }} />
      <Stack.Screen name="overdue" options={{ title: 'Overdue' }} />
    </Stack>
  );
}
