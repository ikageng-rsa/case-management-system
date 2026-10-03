import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import BillingSummaryScreen from '@features/billing/screens/BillingSummaryScreen';
import TariffPickerScreen from '@features/billing/screens/TariffPickerScreen';
import {colors} from '@theme/index';

export type BillingStackParamList = {
    BillingSummary: {caseId?: string} | undefined;
    TariffPicker: undefined;
}

const Stack = createNativeStackNavigator<BillingStackParamList>();

export default function BillingStack(){
    return(
        <Stack.Navigator
            screenOptions ={{
                headerStyle: {backgroundColor: colors.navy},
                headerTintColor: colors.white,
                headerTitleStyle: {fontWeight: '700'},
            }}
        >
            <Stack.Screen name="BillingSummary" component={BillingSummaryScreen} options={{title:'Billing Summary'}} />
            <Stack.Screen name="TariffPicker" component={TariffPickerScreen} options={{title:'Select Tariff Scales'}} />
        </Stack.Navigator>
    );
}