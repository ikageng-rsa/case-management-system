import React from 'react';
import { Stack } from 'expo-router';
import { stackScreenOptions } from '@/navigation/screenOptions';

export default function BillingLayout() {
    return(
        <Stack screenOptions={stackScreenOptions}>
            <Stack.Screen name="index" options={{ title: 'Billing' }} />
            <Stack.Screen name="tariffs" options={{ title: 'Tariff Scales' }} />
        </Stack>
    );
}