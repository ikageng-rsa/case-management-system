import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import DashboardScreen from '@/features/reporting/screens/DashboardScreen';
import { colors } from '@/theme/index';

export type HomeStackParamList = {
  Dashboard: undefined;
};

const Stack = createNativeStackNavigator<HomeStackParamList>();
export default function HomeStack() {
    return(
        <Stack.Navigator
            screenOptions ={{
                headerStyle: {backgroundColor: colors.navy},
                headerTintColor: colors.white,
                headerTitleStyle: {fontWeight: '700'},
            }}
        >
            <Stack.Screen name="Dashboard" component={DashboardScreen} options={{title:'Dashboard',headerShown:false}} />
        </Stack.Navigator>
    )
}