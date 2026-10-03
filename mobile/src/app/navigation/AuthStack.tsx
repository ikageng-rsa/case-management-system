import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import LoginScreen from '@/features/auth/screens/LoginScreen';
import ResetPasswordScreen from '@/features/auth/screens/ResetPasswordScreen';
import {colors} from '@/theme/index';
import { Background } from 'expo-router/build/react-navigation';

export type AuthStackParamList ={
    Login: undefined;
    ResetPassword: undefined;
}

const Stack = createNativeStackNavigator<AuthStackParamList>();

export default function AuthStack(){
    return(
        <Stack.Navigator
        screenOptions ={{
            headerStyle: {backgroundColor: colors.navy},
            headerTintColor: colors.white,
            headerTitleStyle: {fontWeight: '700'},
        }}
        >
            <Stack.Screen name="Login" component={LoginScreen} options={{ headerShown: false}}/>
            <Stack.Screen
                name = "ResetPassword"
                component={ResetPasswordScreen}
                options = {{title: 'Reset Password'}}
            />
        </Stack.Navigator>
    );
}