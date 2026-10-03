import React from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import Card from '@/components/Card';
import { TARIFF_SCALES } from '@/constants/tariffs';
import { colors, spacing, typography } from '@/theme/index';

export default function TariffPickerScreen() {
  const navigation = useNavigation<any>();

  return (
    <FlatList
      style={styles.container}
      contentContainerStyle={styles.list}
      data={TARIFF_SCALES}
      keyExtractor={item => item.code}
      renderItem={({ item }) => (
        <Pressable onPress={() => navigation.navigate('AddNarration', { tariffCode: item.code })}>
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
