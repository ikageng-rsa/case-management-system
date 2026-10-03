import React, { useState } from 'react';
import { Alert, ScrollView, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { createCase } from '@/api/endpoint/cases.api';
import { colors, spacing } from '@/theme/index';

export default function NewCaseScreen() {
  const navigation = useNavigation<any>();
  const [clientName, setClientName] = useState('');
  const [matterType, setMatterType] = useState('');
  const [opposingParty, setOpposingParty] = useState('');
  const [loading, setLoading] = useState(false);

  const submit = async () => {
    if (!clientName || !matterType) {
      Alert.alert('Missing info', 'Client name and matter type are required.');
      return;
    }
    setLoading(true);
    try {
      const created = await createCase({ clientName, matterType, opposingParty });
      navigation.replace('CaseDetail', { caseId: created.id });
    } catch {
      Alert.alert('Could not create case', 'Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Input label="Client name" value={clientName} onChangeText={setClientName} />
      <Input
        label="Matter type"
        value={matterType}
        onChangeText={setMatterType}
        placeholder="e.g. Litigation"
      />
      <Input label="Opposing party (optional)" value={opposingParty} onChangeText={setOpposingParty} />
      <Button label="Create case" onPress={submit} loading={loading} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  content: {
    padding: spacing.lg,
  },
});
