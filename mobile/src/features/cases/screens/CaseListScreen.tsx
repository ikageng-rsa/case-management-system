import React, { useEffect } from 'react';
import { FlatList, StyleSheet, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { loadCases } from '@/features/cases/store/casesSlice';
import CaseCard from '@/features/cases/components/CaseCard';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import Button from '@/components/Button';
import { colors, spacing } from '@/theme/index';

export default function CaseListScreen() {
  const dispatch = useAppDispatch();
  const navigation = useNavigation<any>();
  const { items, status } = useAppSelector(state => state.cases);

  useEffect(() => {
    dispatch(loadCases());
  }, [dispatch]);

  if (status === 'loading' && items.length === 0) {
    return <LoadingSpinner />;
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Button label="+ New Case" onPress={() => navigation.navigate('NewCase')} />
      </View>
      <FlatList
        data={items}
        keyExtractor={item => item.id}
        contentContainerStyle={styles.list}
        onRefresh={() => dispatch(loadCases())}
        refreshing={status === 'loading'}
        renderItem={({ item }) => (
          <CaseCard item={item} onPress={() => navigation.navigate('CaseDetail', { caseId: item.id })} />
        )}
        ListEmptyComponent={
          <EmptyState title="No cases yet" message="Cases you're assigned to will show up here." />
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    paddingHorizontal: spacing.md,
    paddingTop: spacing.md,
  },
  list: {
    padding: spacing.md,
  },
});
