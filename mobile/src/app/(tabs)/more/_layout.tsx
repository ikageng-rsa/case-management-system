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
      <Stack.Screen name="client/[clientId]" options={{ title: 'Client Profile' }} />
      {/* Admin-only: enforced here at the navigator level, not just by hiding the menu item. */}
      <Stack.Protected guard={role ? canAdministerUsers(role) : false}>
        <Stack.Screen name="users" options={{ title: 'Users & Roles' }} />
        <Stack.Screen name="reports" options={{ title: 'Export Reports' }} />
      </Stack.Protected>
      <Stack.Screen name="security" options={{ title: 'Two-Factor Authrntication' }} />
      <Stack.Screen name="notifications" options={{ title: 'Notifications' }} />
      <Stack.Screen name="notification-settings" options={{ title: 'Notification Settings' }} />
      <Stack.Screen name="sync" options={{ title: 'Sync & Offline' }} />
    </Stack>
  );
}
