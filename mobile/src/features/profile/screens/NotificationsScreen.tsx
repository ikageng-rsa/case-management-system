import React, { useCallback, useEffect, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import Card from '@/components/Card';
import Button from '@/components/Button';
import EmptyState from '@/components/EmptyState';
import { fetchNotifications, markAllNotificationsRead, markNotificationRead } from '@/api/endpoint/notifications.api';
import { refreshUnreadCount } from '@/hooks/useUnreadNotifications';
import { useOnSynced } from '@/hooks/useSyncStatus';
import { routeForNotification } from '@/utils/deepLinks';
import { AppNotification } from '@/models/index';
import { colors, spacing, typography } from '@/theme/index';

/** The user's inbox. Items are created and delivered (push / SMS / e-mail) by the backend. */
export default function NotificationsScreen() {
    const router = useRouter();
    const [items, setItems] = useState<AppNotification[]>([]);
    const [loading, setLoading] = useState(true);

    const load = useCallback(async () => {
        try {
            setItems(await fetchNotifications());
        } catch {
            // keep what is shown
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect
        load();
    }, [load]);
    useOnSynced(load);

    const open = async (item: AppNotification) => {
        if (!item.readAt) {
            setItems(list => list.map(n => (n.id === item.id ? { ...n, readAt: new Date().toISOString() } : n)));
            markNotificationRead(item.id).then(refreshUnreadCount).catch(() => { });
        }
        router.push(routeForNotification(item.data));
    };

    const readAll = async () => {
        setItems(list => list.map(n => ({ ...n, readAt: n.readAt ?? new Date().toISOString() })));
        await markAllNotificationsRead().catch(() => { });
        refreshUnreadCount();
    };

    const unread = items.filter(n => !n.readAt).length;

    return (
        <View style={styles.container}>
            <View style={styles.actions}>
                <Button label="Notification settings" variant="secondary" onPress={() => router.push('/more/notification-settings')} style={styles.actionButton} />
                {unread > 0 ? <Button label={`Mark ${unread} as read`} variant="secondary" onPress={readAll} style={styles.actionButton} /> : null}
            </View>
            <FlatList
                data={items}
                keyExtractor={item => item.id}
                contentContainerStyle={styles.list}
                refreshing={loading}
                onRefresh={load}
                renderItem={({ item }) => (
                    <Pressable onPress={() => open(item)} accessibilityRole="button">
                        <Card style={item.readAt ? undefined : styles.unread}>
                            <Text style={styles.title}>{item.title}</Text>
                            <Text style={styles.body}>{item.body}</Text>
                            <Text style={styles.time}>{new Date(item.createdAt).toLocaleString()}</Text>
                        </Card>
                    </Pressable>
                )}
                ListEmptyComponent={
                    loading ? null : <EmptyState title="No notifications" message="Overdue and due-soon diary alerts, and new assignments, will appear here." />
                }
            />
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background },
    actions: { padding: spacing.md, paddingBottom: 0, gap: spacing.sm },
    actionButton: { alignSelf: 'stretch' },
    list: { padding: spacing.md },
    unread: { borderColor: colors.gold },
    title: { ...typography.body, fontWeight: '700', color: colors.textPrimary },
    body: { ...typography.body, color: colors.textSecondary, marginTop: spacing.xs },
    time: { ...typography.caption, color: colors.textMuted, marginTop: spacing.xs },
});
