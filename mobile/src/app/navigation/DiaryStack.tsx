import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import DiaryScreen from '@/features/darzation/screens/DiaryScreen';
import OverdueAlertScreen from '@/features/darzation/screens/OverdueAlertsScreen';
import { colors } from '@/theme/index';

export type DiaryStackParamList = {
  Diary: undefined;
  OverdueAlert: undefined;
};

const Stack = createNativeStackNavigator<DiaryStackParamList>();

export default function DiaryStack() {
    return(
        <Stack.Navigator
            screenOptions ={{
                headerStyle: {backgroundColor: colors.navy},
                headerTintColor: colors.white,
                headerTitleStyle: {fontWeight: '700'},
            }}
        >
            <Stack.Screen name="Diary" component={DiaryScreen} options={{title:'Diary'}} />
            <Stack.Screen name="OverdueAlert" component={OverdueAlertScreen} options={{title:'Overdue Alert'}} />
        </Stack.Navigator>
    )
}