import React from "react";
import { SafeAreaProvider, SafeAreaView } from "react-native-safe-area-context";
import QueryProvider from './providers/QueryProvider';
import ThemeProvider from './providers/ThemeProvider';
import AuthProvider from './providers/AuthProvider';
import RootNavigator from './navigation/RootNavigator';
import OfflineBanner from '@/components/OfflineBanner';

export default function App(){
    return(
        <QueryProvider>
            <AuthProvider>
                <SafeAreaProvider>
                    <ThemeProvider>
                        <SafeAreaView edges={['top']} style={{flex: 0}}>
                            <OfflineBanner/>
                        </SafeAreaView>
                    </ThemeProvider>
                </SafeAreaProvider>
            </AuthProvider>
        </QueryProvider>
    );
}