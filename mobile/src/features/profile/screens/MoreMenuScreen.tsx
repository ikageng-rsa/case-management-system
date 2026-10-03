import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import Card from '@/components/Card';
import Button from '@/components/Button';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { logout } from '@/features/auth/store/authSlice';
import { colors, spacing, typography } from '@/theme/index';

const MENU_ITEMS: { label: string; route: string; adminOnly?: boolean }[] = [
  { label: 'Client Profile', route: 'ClientProfile' },
  { label: 'User & Role Administration', route: 'UserAdmin', adminOnly: true },
  { label: 'Export Reports', route: 'ReportExport' },
];

export default function MoreMenuScreen() {
  const navigation = useNavigation<any>();
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

      {MENU_ITEMS.filter(item => !item.adminOnly || user?.role === 'Admin' || user?.role === 'Director').map(
        item => (
          <Pressable key={item.route} onPress={() => navigation.navigate(item.route)}>
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
