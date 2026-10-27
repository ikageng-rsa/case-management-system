import React, { useEffect, useMemo, useState } from 'react';
import { FlatList, StyleSheet, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { loadCases } from '@/features/cases/store/casesSlice';
import CaseCard from '@/features/cases/components/CaseCard';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { visibleCasesFor } from '@/utils/caseAccess';
import { canEditRecords, hasGlobalVisibility } from '@/constants/roles';
import { colors, spacing } from '@/theme/index';

export default function CaseListScreen() {
  const dispatch = useAppDispatch();
  const router = useRouter();
  const { items, status } = useAppSelector(state => state.cases);
  const me = useAppSelector(state => state.auth.user);
  const [search, setSearch] = useState('');

  const visible = useMemo(() => {
    const scoped = visibleCasesFor(items, me);
    const q = search.trim().toLowerCase();
    if (!q) {
      return scoped;
    }
    return scoped.filter(c =>
      [c.fileReference, c.clientName, c.matterType, c.courtCaseNumber].some(v => v?.toLowerCase().includes(q)),
    );
  }, [items, me, search]);

  useEffect(() => {
    dispatch(loadCases());
  }, [dispatch]);

  if (status === 'loading' && items.length === 0) {
    return <LoadingSpinner />;
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Input label="Search" value={search} onChangeText={setSearch} placeholder="Reference, client, matter, court no." />
        {me && canEditRecords(me.role) ? <Button label="+ New Case" onPress={() => router.push('/cases/new')} /> : null}
      </View>
      <FlatList
        data={visible}
        keyExtractor={item => item.id}
        contentContainerStyle={styles.list}
        onRefresh={() => dispatch(loadCases())}
        refreshing={status === 'loading'}
        renderItem={({ item }) => (
          <CaseCard item={item} onPress={() => router.push({ pathname: '/cases/[caseId]', params: { caseId: item.id } })} />
        )}
        ListEmptyComponent={
          <EmptyState title="No cases yet" message={me && hasGlobalVisibility(me.role) ? 'No cases in the firm yet.' : "Cases you're assigned to will show up here."} />
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
