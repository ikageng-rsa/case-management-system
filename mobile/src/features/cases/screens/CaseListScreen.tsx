import React, { useEffect } from 'react';
import { FlatList, StyleSheet, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { loadCases } from '@/features/cases/store/casesSlice';
import CaseCard from '@/features/cases/components/CaseCard';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import Button from '@/components/Button';
import { colors, spacing } from '@/theme/index';

export default function CaseListScreen() {
  const dispatch = useAppDispatch();
  const router = useRouter();
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
        <Button label="+ New Case" onPress={() => router.push('/cases/new')} />
      </View>
      <FlatList
        data={items}
        keyExtractor={item => item.id}
        contentContainerStyle={styles.list}
        onRefresh={() => dispatch(loadCases())}
        refreshing={status === 'loading'}
        renderItem={({ item }) => (
          <CaseCard item={item} onPress={() => router.push({ pathname: '/cases/[caseId]', params: { caseId: item.id } })} />
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
