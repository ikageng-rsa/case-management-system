import React from 'react';
import { Stack } from 'expo-router';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { canAdministerUsers } from '@/constants/roles';
import { stackScreenOptions } from '@/navigation/screenOptions';

export default function MoreLayout() {
  const role = useAppSelector(state => state.auth.user?.role);

  return (
    <Stack screenOptions={stackScreenOptions}>
      <Stack.Screen name="index" options={{ title: 'More' }} />
      <Stack.Screen name="profile" options={{ title: 'Client Profile' }} />
      {/* Admin-only: enforced here at the navigator level, not just by hiding the menu item. */}
      <Stack.Protected guard={role ? canAdministerUsers(role) : false}>
        <Stack.Screen name="users" options={{ title: 'Users & Roles' }} />
      </Stack.Protected>
      <Stack.Screen name="reports" options={{ title: 'Export Reports' }} />
    </Stack>
  );
}
