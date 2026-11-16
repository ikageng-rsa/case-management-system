import React, { useCallback, useMemo, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';
import { fetchClients } from '@/api/endpoint/clients.api';
import { getApiErrorMessage } from '@/api/errors';
import Card from '@/components/Card';
import EmptyState from '@/components/EmptyState';
import Input from '@/components/Input';
import LoadingSpinner from '@/components/LoadingSpinner';
import { colors, fontFamily, spacing, typography } from '@/theme/index';
import { ClientProfile } from '@/models/index';

/** Clients the signed-in user can see (those on cases they may open), searchable by name. */
export default function ClientListScreen() {
    const router = useRouter();
    const [clients, setClients] = useState<ClientProfile[]>([]);
    const [offline, setOffline] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');

    const load = useCallback(() => {
        fetchClients()
            .then(result => {
                setClients(result.clients);
                setOffline(result.offline);
                setError(null);
            })
            .catch(e => setError(getApiErrorMessage(e, 'Could not load clients.')))
            .finally(() => setLoading(false));
    }, []);

    useFocusEffect(
        useCallback(() => {
            load();
        }, [load]),
    );

    const shown = useMemo(() => {
        const q = search.trim().toLowerCase();
        return q ? clients.filter(c => [c.fullName, c.identifierMasked].some(v => (v ?? '').toLowerCase().includes(q))) : clients;
    }, [clients, search]);

    if (loading) {
        return <LoadingSpinner />;
    }

    return (
        <View style={styles.container}>
            <View style={styles.header}>
                <Input label="Search clients" value={search} onChangeText={setSearch} placeholder="Name" />
                {offline ? <Text style={styles.offline}>Offline: showing the clients saved on this phone.</Text> : null}
                {error ? <Text style={styles.error}>{error}</Text> : null}
            </View>
            <FlatList
                data={shown}
                keyExtractor={c => c.id}
                contentContainerStyle={styles.list}
                onRefresh={load}
                refreshing={false}
                renderItem={({ item }) => (
                    <Pressable
                        accessibilityRole="button"
                        onPress={() => router.push({ pathname: '/more/client/[clientId]', params: { clientId: item.id } })}
                    >
                        <Card>
                            <Text style={styles.name}>{item.fullName}</Text>
                            <Text style={styles.meta}>
                                {item.clientType === 'entity' ? 'Company / entity' : 'Individual'}
                                {item.mattersCount !== undefined ? ` · ${item.mattersCount} matter${item.mattersCount === 1 ? '' : 's'}` : ''}
                            </Text>
                            {item.identifierMasked ? <Text style={styles.meta}>{item.identifierMasked}</Text> : null}
                        </Card>
                    </Pressable>
                )}
                ListEmptyComponent={<EmptyState title="No clients" message="Clients you are allowed to see appear here." />}
            />
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background },
    header: { paddingHorizontal: spacing.md, paddingTop: spacing.md },
    list: { padding: spacing.md },
    name: { ...typography.body, fontFamily: fontFamily.sansSemibold, color: colors.textPrimary },
    meta: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.xs },
    offline: { ...typography.caption, color: colors.textSecondary, marginBottom: spacing.sm },
    error: { ...typography.caption, color: colors.danger, marginBottom: spacing.sm },
});
