import React from 'react';
import { ScrollView, Text, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import LoadingSpinner from '@/components/LoadingSpinner';
import { useClientProfile } from '../hooks/useClientProfile';
import ClientActionsBar from '@/components/ClientActionsBar';
import ClientDetailsCard from '@/components/ClientDetailsCard';
import ClientComplianceCard from '@/components/ClientComplianceCard';
import ClientConsentCard from '@/components/ClientConsentCard';
import ClientContactsSection from '@/components/ClientContactsSection';
import ClientCasesSection from '@/components/ClientCasesSection';
import { styles } from '../clientProfileStyles';

export default function ClientProfileScreen() {
  const { clientId } = useLocalSearchParams<{ clientId: string }>();
  const p = useClientProfile(clientId);

  if (p.loading) {
    return <LoadingSpinner />;
  }
  if (!p.client) {
    return (
      <View style={styles.container}>
        <Text style={styles.note}>{p.failed ?? 'This client is not available.'}</Text>
      </View>
    );
  }
  const { client } = p;

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Text style={styles.title}>{client.fullName}</Text>
      {p.offline ? <Text style={styles.note}>Offline: showing what is saved on this phone. Editing needs a connection.</Text> : null}

      <ClientActionsBar phone={client.contactNumber} email={client.email} />
      <ClientDetailsCard client={client} canEdit={p.canEdit} saving={p.saving} onSave={p.save} />
      <ClientComplianceCard client={client} canCompliance={p.canCompliance} offline={p.offline} saving={p.saving} onSave={p.save} />
      <ClientConsentCard client={client} canEdit={p.canEdit} saving={p.saving} onRecord={p.recordConsent} />
      <ClientContactsSection client={client} canEdit={p.canEdit} onAdd={p.addContact} />
      <ClientCasesSection cases={p.cases} clientName={client.fullName} canCreate={p.canCreateCase} />
    </ScrollView>
  );
}