import type { ComponentProps } from 'react';
import type {Stack} from 'expo-router';
import {colors} from '@/theme/index';

/**
 * Shared header styling for every <Stack> in the route tree (previously
 * copy-pasted into each *Stack.tsx navigator). Import this from a _layout.tsx
 * instead of repeating the options.
 */

export const stackScreenOptions: ComponentProps<typeof Stack>['screenOptions'] = {
    headerStyle: { backgroundColor: colors.navy },
    headerTintColor: colors.white,
    headerTitleStyle: { fontWeight: '700' },
};