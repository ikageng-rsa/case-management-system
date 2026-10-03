import React from 'react';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import CasesListScreen from '@/features/cases/screens/CaseListScreen';
import CaseDetailsScreen from '@/features/cases/screens/CaseDetailsScreen';
import NewCaseScreen from '@/features/cases/screens/NewCaseScreen';
import NarrationLogScreen from '@/features/narrations/screens/NarrationLogScreen';
import AddNarrationScreen from '@/features/narrations/screens/AddNarrationScreen';
import DocumentListScreen from '@/features/documents/screens/DocumentListScreen';
import DocumentViewerScreen from '@/features/documents/screens/DocumentViewerScreen';
import { colors } from '@/theme/index';

export type CasesStackParamList = {
    CaseList: undefined;
    CaseDetails: { caseId: string };
    NewCase: undefined;
    NarrationLog: { caseId: string };
    AddNarration: { caseId: string ,tariffCode?: string};
    DocumentList: { caseId: string };
    DocumentViewer: { documentId: string };
}

const Stack = createNativeStackNavigator<CasesStackParamList>();

export default function CasesStack() {
    return (
        <Stack.Navigator
            screenOptions={{
                headerStyle: { backgroundColor: colors.navy },
                headerTintColor: colors.white,
                headerTitleStyle: { fontWeight: '700' },
            }}
        >
            <Stack.Screen name="CaseList" component={CasesListScreen} options={{ title: 'Cases' }} />
            <Stack.Screen name="CaseDetails" component={CaseDetailsScreen} options={{ title: 'Case Details' }} />
            <Stack.Screen name="NewCase" component={NewCaseScreen} options={{ title: 'New Case' }} />
            <Stack.Screen name="NarrationLog" component={NarrationLogScreen} options={{ title: 'Activity Log' }} />
            <Stack.Screen name="AddNarration" component={AddNarrationScreen} options={{ title: 'Add Activity Log' }} />
            <Stack.Screen name="DocumentList" component={DocumentListScreen} options={{ title: 'Documents' }} />
            <Stack.Screen name="DocumentViewer" component={DocumentViewerScreen} options={{ title: 'Document Viewer' }} />
        </Stack.Navigator>
    );
}