import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import MoreMenuScreen from '@/features/profile/screens/MoreMenuScreen';
import ClientProfileScreen from '@/features/profile/screens/ClientProfileScreen';
import UserAdminScreen from '@/features/profile/screens/UserAdminScreen';
import ReportExportScreen from '@/features/reporting/screens/ReportExportScreen';
import { colors } from '@/theme/index';

export type MoreStackParamList = {
  MoreMenu: undefined;
  ClientProfile: undefined;
  UserAdmin: undefined;
  ReportExport: undefined;
};

const Stack = createNativeStackNavigator<MoreStackParamList>();

export default function MoreStack() {
    return(
        <Stack.Navigator
            screenOptions ={{
                headerStyle: {backgroundColor: colors.navy},
                headerTintColor: colors.white,
                headerTitleStyle: {fontWeight: '700'},
            }}
        >
            <Stack.Screen name="MoreMenu" component={MoreMenuScreen} options={{title:'More'}} />
            <Stack.Screen name="ClientProfile" component={ClientProfileScreen} options={{title:'Client Profile'}} />
            <Stack.Screen name="UserAdmin" component={UserAdminScreen} options={{title:'User Administration'}} />
            <Stack.Screen name="ReportExport" component={ReportExportScreen} options={{title:'Report Export'}} />
        </Stack.Navigator>
    );
}