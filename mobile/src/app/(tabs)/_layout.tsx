import React, { ComponentProps } from 'react';
import { ColorValue } from 'react-native';
import { Tabs } from 'expo-router';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { canAccessTab } from '@/constants/roles';
import { colors } from '@/theme/index';

type IconName = ComponentProps<typeof MaterialCommunityIcons>['name'];

// Built once at module scope (not inline in screenOptions) so React doesn't
// see a new component type on every TabsLayout render.
function makeTabIcon(name: IconName) {
  return function TabIcon({ color, size }: { color: ColorValue; size: number }) {
    return <MaterialCommunityIcons name={name} color={color} size={size} />;
  };
}

const ICONS = {
  home: makeTabIcon('view-dashboard-outline'),
  cases: makeTabIcon('briefcase-outline'),
  diary: makeTabIcon('calendar-clock-outline'),
  billing: makeTabIcon('cash-multiple'),
  more: makeTabIcon('dots-horizontal-circle-outline'),
};

export default function TabsLayout() {
  const role = useAppSelector(state => state.auth.user?.role);
  const can = (tab: string) => (role ? canAccessTab(tab, role) : false);

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.navy,
        tabBarInactiveTintColor: colors.textSecondary,
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Home', tabBarIcon: ICONS.home }} />
      {/* Tabs.Protected removes the tab (and blocks its URLs) for roles without access —
          keep TAB_ACCESS in constants/roles.ts as the single source of truth. */}
      <Tabs.Protected guard={can('Cases')}>
        <Tabs.Screen name="cases" options={{ title: 'Cases', tabBarIcon: ICONS.cases }} />
      </Tabs.Protected>
      <Tabs.Protected guard={can('Diary')}>
        <Tabs.Screen name="diary" options={{ title: 'Diary', tabBarIcon: ICONS.diary }} />
      </Tabs.Protected>
      <Tabs.Protected guard={can('Billing')}>
        <Tabs.Screen name="billing" options={{ title: 'Billing', tabBarIcon: ICONS.billing }} />
      </Tabs.Protected>
      <Tabs.Screen name="more" options={{ title: 'More', tabBarIcon: ICONS.more }} />
    </Tabs>
  );
}
