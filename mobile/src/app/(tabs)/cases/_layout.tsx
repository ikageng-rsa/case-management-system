import React from 'react';
import { Stack } from 'expo-router';
import { stackScreenOptions } from '@/navigation/screenOptions';

export default function CasesLayout() {
  return (
    <Stack screenOptions={stackScreenOptions}>
      <Stack.Screen name="index" options={{ title: 'Cases' }} />
      <Stack.Screen name="new" options={{ title: 'New Case' }} />
      <Stack.Screen name="[caseId]/index" options={{ title: 'Case' }} />
      <Stack.Screen name="[caseId]/activity" options={{ title: 'Activity Log' }} />
      <Stack.Screen name="[caseId]/add-activity" options={{ title: 'Log Activity' }} />
      <Stack.Screen name="[caseId]/documents/index" options={{ title: 'Documents' }} />
      <Stack.Screen name="[caseId]/documents/[documentId]" options={{ title: 'Document' }} />
    </Stack>
  );
}
