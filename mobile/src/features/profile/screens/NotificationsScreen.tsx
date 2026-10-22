import React, { useCallback, useEffect, useState } from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import Card from '@/components/Card';
import EmptyState from '@/components/EmptyState';
import { getNotificationLog, markAllNotificationsRead } from '@/services/notifications/notificationLog';
import { AppNotification } from '@/models/index';
import { colors, spacing, typography } from '@/theme/index';

const CHANNEL_LABEL: Record<AppNotification['channel'], string> = {
    push: 'Popup',
    'in-app': 'Popup',
    sms: 'SMS',
};

export default function NotificationsScreen() {
    const [items, setItems] = useState<AppNotification[]>([]);

    const load = useCallback(async () => {
        setItems(await getNotificationLog());
        await markAllNotificationsRead();
    }, []);

    // Fetch-on-mount: setState runs after awaited network I/O, not synchronously.
    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect
        load();
    }, [load]);

    return (
        <View style={styles.container}>
            <FlatList
                data={items}
                keyExtractor={item => item.id}
                contentContainerStyle={styles.list}
                renderItem={({ item }) => (
                    <Card style={item.read ? undefined : styles.unread}>
                        <View style={styles.row}>
                            <Text style={styles.title}>{item.title}</Text>
                            <Text style={styles.channel}>{CHANNEL_LABEL[item.channel]}</Text>
                        </View>
                        <Text style={styles.body}>{item.body}</Text>
                        <Text style={styles.time}>{new Date(item.createdAt).toLocaleString()}</Text>
                    </Card>
                )}
                ListEmptyComponent={<EmptyState title="No notifications" message="Overdue alerts will appear here." />}
            />
        </View>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: colors.background },
    list: { padding: spacing.md },
    unread: { borderColor: colors.gold },
    row: { flexDirection: 'row', justifyContent: 'space-between' },
    title: { ...typography.body, fontWeight: '700', color: colors.textPrimary },
    channel: { ...typography.caption, color: colors.navy, fontWeight: '600' },
    body: { ...typography.body, color: colors.textSecondary, marginTop: spacing.xs },
    time: { ...typography.caption, color: colors.textMuted, marginTop: spacing.xs },
});
