import React, { useCallback, useMemo, useState } from 'react';
import { FlatList, StyleSheet, View } from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';
import { fetchCases, fetchMatterCounts, MatterCounts, MatterFilter } from '@/api/endpoint/cases.api';
import { getApiErrorMessage } from '@/api/errors';
import { can } from '@/config/features';
import ChipSelect from '@/components/ChipSelect';
import { useAppDispatch, useAppSelector } from '@/hooks/useAppDispatch';
import { loadCases } from '@/features/cases/store/casesSlice';
import CaseCard from '@/features/cases/components/CaseCard';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import Input from '@/components/Input';
import { visibleCasesFor } from '@/utils/caseAccess';
import { Case } from '@/models/index';
import { colors, spacing } from '@/theme/index';

export default function CaseListScreen() {
  const dispatch = useAppDispatch();
  const router = useRouter();
  const { items, status } = useAppSelector(state => state.cases);
  const me = useAppSelector(state => state.auth.user);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState<MatterFilter[]>(['open']);
  const [counts, setCounts] = useState<MatterCounts | null>(null);
  const [other, setOther] = useState<Case[]>([]);
  const [otherLoading, setOtherLoading] = useState(false);
  const [otherError, setOtherError] = useState<string | null>(null);
  const active = filter[0] ?? 'open';

  const options = useMemo(() => {
    const n = (value?: number) => (value === undefined ? '' : ` (${value})`);
    const all: { value: MatterFilter; label: string }[] = [
      { value: 'open', label: `Open${n(counts?.open)}` },
      { value: 'mine', label: `Mine${n(counts?.mine)}` },
      { value: 'closed', label: `Closed${n(counts?.closed)}` },
    ];
    // Only people who can assign matters have use for the unassigned list.
    if (can(me?.permissions, 'assign_matters')) {
      all.splice(2, 0, { value: 'unassigned', label: `Unassigned${n(counts?.unassigned)}` });
    }
    return all;
  }, [counts, me?.permissions]);

  const loadOther = useCallback(async () => {
    if (active === 'open') {
      return;
    }
    setOtherLoading(true);
    try {
      setOther(await fetchCases({ filter: active }));
      setOtherError(null);
    } catch (e) {
      setOther([]);
      setOtherError(getApiErrorMessage(e, 'These matters could not be loaded.'));
    } finally {
      setOtherLoading(false);
    }
  }, [active]);

  const visible = useMemo(() => {
    // Open matters are scoped on the phone too; the other lists are already scoped by the server and a matter
    // the user can see but holds no capacity on (e.g. closed) must not be hidden.
    const scoped = active === 'open' ? visibleCasesFor(items, me) : other;
    const q = search.trim().toLowerCase();
    if (!q) {
      return scoped;
    }
    return scoped.filter(c =>
      [c.fileReference, c.title, c.clientName, c.matterType, c.courtLabel].some(v => v?.toLowerCase().includes(q)),
    );
  }, [active, items, other, me, search]);

  // Reload every time the screen is shown, so a case just created or edited is up to date.
  useFocusEffect(
    useCallback(() => {
      dispatch(loadCases());
      fetchMatterCounts().then(setCounts);
      loadOther();
    }, [dispatch, loadOther]),
  );

  if (active === 'open' && status === 'loading' && items.length === 0) {
    return <LoadingSpinner />;
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <ChipSelect label="Show" options={options} selected={filter} onChange={setFilter} />
        <Input label="Search" value={search} onChangeText={setSearch} placeholder="Reference, title, client, matter" />
      </View>
      <FlatList
        data={visible}
        keyExtractor={item => item.id}
        contentContainerStyle={styles.list}
        onRefresh={() => (active === 'open' ? dispatch(loadCases()) : loadOther())}
        refreshing={active === 'open' ? status === 'loading' : otherLoading}
        renderItem={({ item }) => (
          <CaseCard item={item} onPress={() => router.push({ pathname: '/cases/[caseId]', params: { caseId: item.id } })} />
        )}
        ListEmptyComponent={
          <EmptyState
            title={active === 'open' ? 'No matters yet' : 'Nothing here'}
            message={otherError && active !== 'open' ? otherError : 'Matters you are allowed to see will show up here.'}
          />
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
