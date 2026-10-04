import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Href, useRouter } from 'expo-router';
import Card from '@/components/Card';
import Button from '@/components/Button';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { logout } from '@/features/auth/store/authSlice';
import { canAdministerUsers } from '@/constants/roles';
import { colors, spacing, typography } from '@/theme/index';

const MENU_ITEMS: { label: string; href: Href; adminOnly?: boolean }[] = [
  { label: 'Client Profile', href: '/more/profile' },
  { label: 'User & Role Administration', href: '/more/users', adminOnly: true },
  { label: 'Export Reports', href: '/more/reports' },
];

export default function MoreMenuScreen() {
  const router = useRouter();
  const dispatch = useAppDispatch();
  const user = useAppSelector(state => state.auth.user);

  return (
    <View style={styles.container}>
      {user ? (
        <Card style={styles.userCard}>
          <Text style={styles.userName}>{user.fullName}</Text>
          <Text style={styles.userRole}>{user.role}</Text>
        </Card>
      ) : null}

      {MENU_ITEMS.filter(item => !item.adminOnly || (user ? canAdministerUsers(user.role) : false)).map(
        item => (
          <Pressable key={item.label} onPress={() => router.push(item.href)}>
            <Card>
              <Text style={styles.menuLabel}>{item.label}</Text>
            </Card>
          </Pressable>
        ),
      )}

      <Button label="Sign out" variant="danger" onPress={() => dispatch(logout())} style={styles.logout} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, padding: spacing.lg },
  userCard: { alignItems: 'center' },
  userName: { ...typography.h2, color: colors.navy },
  userRole: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.xs },
  menuLabel: { ...typography.body, color: colors.textPrimary, fontWeight: '600' },
  logout: { marginTop: spacing.lg },
});
