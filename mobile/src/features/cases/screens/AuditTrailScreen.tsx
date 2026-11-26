import React, { useCallback, useEffect, useState } from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import { AuditChange, AuditEntry, fetchAuditPage } from '@/api/endpoint/audit.api';
import { getApiErrorMessage } from '@/api/errors';
import Button from '@/components/Button';
import Card from '@/components/Card';
import EmptyState from '@/components/EmptyState';
import LoadingSpinner from '@/components/LoadingSpinner';
import { StageBadge } from '@/ui';
import { formatDue } from '@/utils/dueAt';
import { colors, spacing, typography } from '@/theme/index';

const show = (value: unknown): string => (value === null || value === undefined || value === '' ? '—' : String(value));
const label = (s: string) => s.replace(/[_-]+/g, ' ');

function changeLine(c: AuditChange): string {
    return `${label(c.field)}: ${show(c.old)} → ${show(c.new)}`;
}

/** Who did what on a matter, newest first. Only reachable when the matter says `can.view_audit`. */
export default function AuditTrailScreen() {
    const { caseId } = useLocalSearchParams<{ caseId: string }>();
    const [entries, setEntries] = useState<AuditEntry[]>([]);
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [loading, setLoading] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const load = useCallback(
        async (next: number) => {
            try {
                const result = await fetchAuditPage(caseId, next);
                setEntries(prev => (next === 1 ? result.entries : [...prev, ...result.entries]));
                setHasMore(result.hasMore);
                setPage(next);
                setError(null);
            } catch (e) {
                setError(getApiErrorMessage(e, 'The audit trail could not be loaded.'));
            } finally {
                setLoading(false);
                setLoadingMore(false);
            }
        },
        [caseId],
    );

    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect
        load(1);
    }, [load]);

    if (loading) {
        return <LoadingSpinner />;
    }

    return (
        <View style={styles.container}>
            <FlatList
                data={entries}
                keyExtractor={item => item.id}
                contentContainerStyle={styles.list}
                renderItem={({ item }) => (
                    <Card>
                        <View style={styles.top}>
                            <StageBadge tone={item.event === 'deleted' ? 'danger' : item.event === 'created' || item.event === 'uploaded' ? 'success' : 'neutral'}>
                                {label(item.event)}
                            </StageBadge>
                            <Text style={styles.meta}>{formatDue(item.createdAt)}</Text>
                        </View>
                        <Text style={styles.description}>{item.description}</Text>
                        {item.changes.map(c => (
                            <Text key={c.field} style={styles.change}>
                                {changeLine(c)}
                            </Text>
                        ))}
                        <Text style={styles.meta}>
                            {[label(item.subjectType), item.field ? `field ${label(item.field)}` : null, item.via ? `via ${item.via}` : null].filter(Boolean).join(' · ')}
                        </Text>
                    </Card>
                )}
                ListEmptyComponent={<EmptyState title={error ? 'Not available' : 'Nothing recorded'} message={error ?? 'No activity has been recorded on this matter.'} />}
                ListFooterComponent={
                    hasMore ? (
                        <Button label="Load older entries" variant="secondary" loading={loadingMore} onPress={() => {
                            setLoadingMore(true);
                            load(page + 1);
                        }} />
                    ) : undefined
                }
            />
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background },
    list: { padding: spacing.md },
    top: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: spacing.xs },
    description: { ...typography.body, color: colors.textPrimary },
    change: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.xs },
    meta: { ...typography.caption, color: colors.textMuted, marginTop: spacing.xs },
});
