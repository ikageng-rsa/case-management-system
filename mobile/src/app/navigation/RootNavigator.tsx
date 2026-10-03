import React from 'react';
import {NavigationContainer} from '@react-navigation/native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import AuthStack from './AuthStack';
import MainTabNavigator from './MainTabNavigator';
import linking from './linking';
import {useAppSelector} from '@/hooks/useAppDispatch';
import LoadingSpinner from '@/components/LoadingSpinner';

export type RootStackParamList = {
    Auth: undefined;
    Main: undefined;
};

const Stack = createNativeStackNavigator<RootStackParamList>();

export default function RootNavigator() {
    const {user,status} = useAppSelector(state => state.auth);

    //loading here covers the initial silent-login check performed by
    //AuthProvider on app start (reading a persisted token before deciding
    //which stack to mount) - see app/providers/AuthProvider.tsx .

    if(status === 'loading' && !user){
        return <LoadingSpinner/>;
    }

    return (
        <NavigationContainer linking={linking}>
            <Stack.Navigator screenOptions={{headerShown: false}}>
                {user ? (
                    <Stack.Screen name="Main" component={MainTabNavigator} />
                ) : (
                    <Stack.Screen name="Auth" component={AuthStack} />
                )}
            </Stack.Navigator>
        </NavigationContainer>
    );
}