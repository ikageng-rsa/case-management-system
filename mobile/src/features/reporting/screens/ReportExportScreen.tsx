import React, { useCallback, useMemo, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from 'expo-router';
import { fetchCases } from '@/api/endpoint/cases.api';
import { fetchDiary } from '@/api/endpoint/darzations.api';
import { fetchNarrationsForCase } from '@/api/endpoint/narrations.api';
import { fetchUsers } from '@/api/endpoint/users.api';
import { getApiErrorMessage } from '@/api/errors';
import Button from '@/components/Button';
import Card from '@/components/Card';
import ChipSelect from '@/components/ChipSelect';
import LoadingSpinner from '@/components/LoadingSpinner';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { visibleCasesFor, visibleDarzationsFor, visibleNarrationsFor } from '@/utils/caseAccess';
import { colors, fontFamily, radius, spacing, typography } from '@/theme/index';
import { Case, Darzation, Narration, User } from '@/models/index';
import { buildReport, PERIOD_LABELS, REPORT_LABELS, ReportKind, TimePeriod } from '../services/reportBuilder';
import { ExportCancelledError, ExportFormat, exportReport } from '../services/exportReport';
import { notifyError } from '@/utils/notify';

const KINDS = Object.keys(REPORT_LABELS) as ReportKind[];
const PERIODS = Object.keys(PERIOD_LABELS) as TimePeriod[];

/**
 * Builds a report from the data already scoped to this user (the same rules as the lists and dashboard),
 * previews it, and exports it as CSV or PDF through the phone's share sheet. Works from the on-device copy
 * when offline.
 */
export default function ReportExportScreen() {
  const me = useAppSelector(state => state.auth.user);
  const [cases, setCases] = useState<Case[]>([]);
  const [diary, setDiary] = useState<Darzation[]>([]);
  const [narrations, setNarrations] = useState<Narration[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [kind, setKind] = useState<ReportKind[]>(['caseLoad']);
  const [period, setPeriod] = useState<TimePeriod[]>(['month']);
  const [format, setFormat] = useState<ExportFormat[]>(['csv']);
  const [exporting, setExporting] = useState(false);

  const load = useCallback(() => {
    if (!me || me.role === 'Messenger') {
      setLoading(false);
      return;
    }
    (async () => {
      try {
        const [allCases, allDiary, allUsers] = await Promise.all([fetchCases(), fetchDiary(), fetchUsers().catch(() => [me])]);
        const myCases = visibleCasesFor(allCases, me);
        const perCase = await Promise.all(myCases.map(c => fetchNarrationsForCase(c.id).catch(() => [] as Narration[])));
        setCases(myCases);
        setDiary(visibleDarzationsFor(allDiary, allCases, me));
        setNarrations(visibleNarrationsFor(perCase.flat(), allCases, me));
        setUsers(allUsers.length ? allUsers : [me]);
        setError(null);
      } catch (e) {
        setError(getApiErrorMessage(e, 'Could not load the data for reports.'));
      } finally {
        setLoading(false);
      }
    })();
  }, [me]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  const table = useMemo(
    () =>
      buildReport(kind[0] ?? 'caseLoad', {
        cases,
        diary,
        narrations,
        users,
        generatedBy: me?.fullName ?? 'unknown',
        period: period[0] ?? 'month',
      }),
    [kind, period, cases, diary, narrations, users, me],
  );

  if (loading) {
    return <LoadingSpinner />;
  }
  if (!me || me.role === 'Messenger') {
    return (
      <View style={styles.container}>
        <Text style={styles.note}>Your role does not have access to case or diary reports.</Text>
      </View>
    );
  }

  const run = async () => {
    setExporting(true);
    try {
      await exportReport(kind[0] ?? 'caseLoad', table, format[0] ?? 'csv');
    } catch (e) {
      if (!(e instanceof ExportCancelledError)) {
        notifyError('Could not export', getApiErrorMessage(e, 'Please try again.'));
      }
    } finally {
      setExporting(false);
    }
  };

  const preview = table.rows.slice(0, 5);

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Export reports</Text>
      <Text style={styles.note}>
        Reports only include what you are allowed to see. The file is created on this phone and handed to the app you
        choose (e-mail, WhatsApp, Drive, Files…). Exports contain client information: share them carefully.
      </Text>
      {error ? <Text style={styles.error}>{error}</Text> : null}

      <ChipSelect label="Report" options={KINDS.map(k => ({ value: k, label: REPORT_LABELS[k] }))} selected={kind} onChange={setKind} />
      {kind[0] === 'time' ? (
        <ChipSelect label="Period" options={PERIODS.map(p => ({ value: p, label: PERIOD_LABELS[p] }))} selected={period} onChange={setPeriod} />
      ) : null}
      <ChipSelect
        label="Format"
        options={[
          { value: 'csv', label: 'CSV (Excel)' },
          { value: 'pdf', label: 'PDF' },
        ]}
        selected={format}
        onChange={setFormat}
      />

      <Card style={styles.preview}>
        <Text style={styles.previewTitle}>{table.title}</Text>
        <Text style={styles.previewMeta}>{table.subtitle}</Text>
        {preview.length === 0 ? <Text style={styles.previewMeta}>Nothing to report for this selection.</Text> : null}
        {preview.map((row, i) => (
          <Text key={i} style={styles.previewRow} numberOfLines={2}>
            {row.slice(0, 4).filter(Boolean).join(' · ')}
          </Text>
        ))}
        {table.rows.length > preview.length ? <Text style={styles.previewMeta}>…and {table.rows.length - preview.length} more in the file</Text> : null}
      </Card>

      <Button
        label={`Export ${REPORT_LABELS[kind[0] ?? 'caseLoad']} as ${(format[0] ?? 'csv').toUpperCase()}`}
        onPress={run}
        loading={exporting}
        disabled={table.rows.length === 0}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.lg, paddingBottom: spacing.xl * 2 },
  title: { ...typography.h1, color: colors.navy, marginBottom: spacing.sm },
  note: { ...typography.body, color: colors.textSecondary, marginBottom: spacing.lg },
  error: { ...typography.caption, color: colors.danger, marginBottom: spacing.md },
  preview: { marginBottom: spacing.lg, borderRadius: radius.md },
  previewTitle: { ...typography.body, fontFamily: fontFamily.sansSemibold, color: colors.navy },
  previewMeta: { ...typography.caption, color: colors.textSecondary, marginVertical: spacing.xs },
  previewRow: { ...typography.caption, color: colors.textPrimary, paddingVertical: 2 },
});
