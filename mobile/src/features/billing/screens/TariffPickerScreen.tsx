import React from 'react';
import { Alert, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import Card from '@/components/Card';
import { TARIFF_SCALES } from '@/constants/tariffs';
import { colors, spacing, typography } from '@/theme/index';

export default function TariffPickerScreen() {
  const router = useRouter();
  const { caseId } = useLocalSearchParams<{ caseId?: string }>();

  const pick = (tariffCode: string) => {
    // Logging an activity is case-scoped, so a tariff can only be applied when we know the case.
    if (!caseId) {
      Alert.alert('Select a case first', 'Open a case and log the activity from there to apply a tariff.');
      return;
    }
    router.push({ pathname: '/cases/[caseId]/add-activity', params: { caseId, tariffCode } });
  };

  return (
    <FlatList
      style={styles.container}
      contentContainerStyle={styles.list}
      data={TARIFF_SCALES}
      keyExtractor={item => item.code}
      renderItem={({ item }) => (
        <Pressable onPress={() => pick(item.code)}>
          <Card>
            <View style={styles.row}>
              <Text style={styles.label}>{item.label}</Text>
              <Text style={styles.rate}>
                R {item.ratePerUnit.toFixed(2)} / {item.unit}
              </Text>
            </View>
            <Text style={styles.code}>{item.code}</Text>
          </Card>
        </Pressable>
      )}
    />
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  list: { padding: spacing.md },
  row: { flexDirection: 'row', justifyContent: 'space-between' },
  label: { ...typography.body, color: colors.textPrimary, fontWeight: '600' },
  rate: { ...typography.body, color: colors.textSecondary },
  code: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.xs },
});
