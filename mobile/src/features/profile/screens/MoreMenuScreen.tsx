import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Href, useRouter } from 'expo-router';
import Card from '@/components/Card';
import Button from '@/components/Button';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { logout } from '@/features/auth/store/authSlice';
import { hasFeature } from '@/config/features';
import { useSyncStatus } from '@/hooks/useSyncStatus';
import { useUnreadNotifications } from '@/hooks/useUnreadNotifications';
import { colors, fontFamily, spacing, typography } from '@/theme/index';
import { confirmAction } from '@/ui';

const MENU_ITEMS: { label: string; href: Href; feature?: 'notifications' }[] = [
  { label: 'Clients', href: '/more/profile' },
  { label: 'Notifications', href: '/more/notifications', feature: 'notifications' },
  { label: 'Sync & Offline', href: '/more/sync' },
  { label: 'Security', href: '/more/security' },
  { label: 'Export Reports', href: '/more/reports' },
];

export default function MoreMenuScreen() {
  const router = useRouter();
  const dispatch = useAppDispatch();
  const user = useAppSelector(state => state.auth.user);
  const { pending, failed, blocked } = useSyncStatus();
  const unsynced = pending + failed + blocked;
  const unread = useUnreadNotifications();

  const signOut = async () => {
    if (unsynced === 0) {
      dispatch(logout());
      return;
    }
    const ok = await confirmAction({
      title: 'Unsynced changes',
      message: `${unsynced} change${unsynced === 1 ? ' hasn' : 's haven'}'t reached the server yet. They stay safely on this device and sync after you sign in again with a connection.`,
      actionLabel: 'Sign out',
      cancelLabel: 'Stay signed in',
      icon: 'log-out',
    });
    if (ok) {
      dispatch(logout());
    }
  };

  return (
    <View style={styles.container}>
      {user ? (
        <Card style={styles.userCard}>
          <Text style={styles.userName}>{user.fullName}</Text>
          <Text style={styles.userRole}>{user.jobTitle || user.roles?.[0] || user.role}</Text>
        </Card>
      ) : null}

      {MENU_ITEMS.filter(item => !item.feature || hasFeature(item.feature)).map(
        item => (
          <Pressable key={item.label} onPress={() => router.push(item.href)}>
            <Card>
              <Text style={styles.menuLabel}>
                {item.label}
                {item.label === 'Sync & Offline' && unsynced > 0 ? `  ·  ${unsynced} waiting` : ''}
                {item.label === 'Notifications' && unread > 0 ? `  ·  ${unread} new` : ''}
              </Text>
            </Card>
          </Pressable>
        ),
      )}

      <Button label="Sign out" variant="danger" onPress={signOut} style={styles.logout} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, padding: spacing.lg },
  userCard: { alignItems: 'center' },
  userName: { ...typography.h2, color: colors.navy },
  userRole: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.xs },
  menuLabel: { ...typography.body, color: colors.textPrimary, fontFamily: fontFamily.sansSemibold },
  logout: { marginTop: spacing.lg },
});
