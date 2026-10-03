import React, { useState } from 'react';
import { Alert, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { addNarration } from '@/api/endpoint/narrations.api';
import { colors, spacing, typography } from '@/theme/index';
import { Narration } from '@/models/index';

const ACTIVITY_TYPES: Narration['activityType'][] = ['Call', 'Draft', 'Appearance', 'Travel', 'Other'];

export default function AddNarrationScreen() {
  const navigation = useNavigation<any>();
  const route = useRoute<any>();
  const { caseId } = route.params as { caseId: string };

  const [activityType, setActivityType] = useState<Narration['activityType']>('Call');
  const [description, setDescription] = useState('');
  const [billable, setBillable] = useState(true);
  const [loading, setLoading] = useState(false);

  const submit = async () => {
    if (!description) {
      Alert.alert('Add a description', 'A short note on what was done is required.');
      return;
    }
    setLoading(true);
    try {
      await addNarration({
        caseId,
        authorId: 'current-user', // replaced by the authenticated user's id
        activityType,
        description,
        billable,
      });
      navigation.goBack();
    } catch {
      Alert.alert('Could not save', 'Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.label}>Activity type</Text>
      <View style={styles.typeRow}>
        {ACTIVITY_TYPES.map(type => (
          <Button
            key={type}
            label={type}
            variant={type === activityType ? 'primary' : 'secondary'}
            onPress={() => setActivityType(type)}
            style={styles.typeButton}
          />
        ))}
      </View>

      <Input
        label="Description"
        value={description}
        onChangeText={setDescription}
        multiline
        numberOfLines={4}
      />

      <View style={styles.switchRow}>
        <Text style={styles.label}>Billable</Text>
        <Switch value={billable} onValueChange={setBillable} />
      </View>

      <Button label="Save entry" onPress={submit} loading={loading} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.lg },
  label: { ...typography.caption, color: colors.textSecondary, marginBottom: spacing.xs },
  typeRow: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs, marginBottom: spacing.md },
  typeButton: { paddingHorizontal: spacing.sm, paddingVertical: spacing.xs },
  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.lg,
  },
});
