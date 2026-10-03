import React from "react";
import { createBottomTabNavigator } from "expo-router/build/react-navigation/bottom-tabs";
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';
import HomeStack from "./HomeStack";
import CasesStack from "./CasesStack";
import DiaryStack from "./DiaryStack";
import BillingStack from "./BillingStack";
import MoreStack from "./MoreStack";
import {useAppSelector} from '@/hooks/useAppDispatch';
import {canAccessTab} from '@/constants/roles';
import {colors} from '@/theme/index';

export type MainTabParamList ={
    Home: undefined;
    Cases: undefined;
    Diary: undefined;
    Billing: undefined;
    More: undefined;
}

const Tab = createBottomTabNavigator<MainTabParamList>();

const TAB_ICONS: Record<keyof MainTabParamList, string> ={
    Home: 'view-dashboard-outline',
    Cases: 'briefcase-outline',
    Diary: 'calendar-clock-outline',
    Billing: 'cash-multiple',
    More: 'dots-horizontal-circle-outline',
}

//Defined once at module scope (not inline in screenOptions) so React
//doesn't see a new component type on every MainTabNavigator rendor.
function renderTabIcon(routeName: keyof MainTabParamList, {color,size}:{color: string; size:number}){
    return <Icon name={TAB_ICONS[routeName]} color={color} size={size}/>;
}

export default function MainTabNavigator(){
    const role = useAppSelector(state => state.auth.user?.role);

    return(
        <Tab.Navigator
            screenOptions={({route}) => ({
                headerShown: false,
                tabBarActiveTintColor: colors.navy,
                tabBarInactiveTintColor: colors.textSecondary,
                tabBarIcon: props => renderTabIcon(route.name as keyof MainTabParamList, props as {color: string; size:number}),
            })}
        >
            <Tab.Screen name="Home" component={HomeStack} options={{title:'Dashboard', tabBarIcon: props => renderTabIcon('Home',  props as {color: string; size:number})}}/>
            {canAccessTab(role, 'Cases') && <Tab.Screen name="Cases" component={CasesStack} options={{title:'Cases', tabBarIcon: props => renderTabIcon('Cases',  props as {color: string; size:number})}}/>}
            {canAccessTab(role, 'Diary') && <Tab.Screen name="Diary" component={DiaryStack} options={{title:'Diary', tabBarIcon: props => renderTabIcon('Diary',  props as {color: string; size:number})}}/>}
            {canAccessTab(role, 'Billing') && <Tab.Screen name="Billing" component={BillingStack} options={{title:'Billing', tabBarIcon: props => renderTabIcon('Billing', props as {color: string; size:number})}}/>}
            <Tab.Screen name="More" component={MoreStack} options={{title:'More', tabBarIcon: props => renderTabIcon('More', props as {color: string; size:number})}}/>

        </Tab.Navigator>
    );
}