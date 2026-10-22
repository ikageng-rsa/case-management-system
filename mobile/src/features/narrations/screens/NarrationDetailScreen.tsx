import React, { useCallback, useEffect, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as DocumentPicker from 'expo-document-picker';
import * as Sharing from 'expo-sharing';
import { fetchNarrationById } from '@/api/endpoint/narrations.api';
import Card from '@/components/Card';
import SyncBadge from '@/components/SyncBadge';
import { useOnSynced } from '@/hooks/useSyncStatus';
import { isDeviceOffline } from '@/offline/connectivity';
import Button from '@/components/Button';
import DetailRow from '@/components/DetailRow';
import LoadingSpinner from '@/components/LoadingSpinner';
import SectionHeader from '@/components/SectionHeader';
import {
  attachDocumentToNarration,
  getDocumentsForNarration,
  removeNarrationDocument,
  retryPendingSync,
} from '@/features/documents/services/narrationDocuments';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { canEditRecords } from '@/constants/roles';
import { formatDuration } from '@/utils/formatDuration';
import { colors, spacing, typography } from '@/theme/index';
import { CaseDocument, Narration } from '@/models/index';

const SYNC_LABEL: Record<CaseDocument['syncStatus'], string> = {
  'local-only': 'Saved on device',
  'pending-sync': 'Waiting to upload',
  synced: 'Uploaded',
  'sync-failed': 'Upload failed — will retry',
};

export default function NarrationDetailScreen() {
  const router = useRouter();
  const { caseId, narrationId } = useLocalSearchParams<{ caseId: string; narrationId: string }>();
  const me = useAppSelector(state => state.auth.user);
  const [item, setItem] = useState<Narration | null>(null);
  const [docs, setDocs] = useState<CaseDocument[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    try {
      await retryPendingSync(caseId, narrationId);
      setDocs(await getDocumentsForNarration(narrationId));
      setItem(await fetchNarrationById(caseId, narrationId));
    } catch {
      setItem(null);
    } finally {
      setLoading(false);
    }
  }, [caseId, narrationId]);

  // Fetch-on-mount: setState runs after awaited network I/O, not synchronously.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);
  useOnSynced(load);

  if (loading) {
    return <LoadingSpinner />;
  }
  if (!item) {
    return (
      <View style={styles.container}>
        <Text style={styles.note}>This entry is not available.</Text>
      </View>
    );
  }

  const canEdit = me ? canEditRecords(me.role) : false;

  const addDocuments = async () => {
    const result = await DocumentPicker.getDocumentAsync({ multiple: true, copyToCacheDirectory: true });
    if (result.canceled) {
      return;
    }
    for (const asset of result.assets) {
      try {
        await attachDocumentToNarration({
          caseId,
          narrationId,
          file: { uri: asset.uri, name: asset.name, mimeType: asset.mimeType, size: asset.size },
          confidentiality: 'Standard',
          uploadedById: me?.id ?? '',
        });
      } catch {
        Alert.alert('Could not store file', asset.name);
      }
    }
    setDocs(await getDocumentsForNarration(narrationId));
  };

  const openDoc = async (doc: CaseDocument) => {
    const uri = doc.localUri ?? doc.uri;
    if (await Sharing.isAvailableAsync()) {
      await Sharing.shareAsync(uri, { mimeType: doc.mimeType, dialogTitle: doc.fileName });
    } else {
      Alert.alert('Cannot open', 'Sharing/opening files is not available on this device.');
    }
  };

  const removeDoc = async (doc: CaseDocument) => {
    // Deleting is an online-only action; an attachment that never left this device is just a local draft.
    if (doc.syncStatus === 'synced' && (await isDeviceOffline())) {
      Alert.alert('Offline', 'Removing a saved document needs an internet connection.');
      return;
    }
    Alert.alert('Remove document?', doc.fileName, [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Remove',
        style: 'destructive',
        onPress: async () => {
          await removeNarrationDocument(narrationId, doc.id);
          setDocs(await getDocumentsForNarration(narrationId));
        },
      },
    ]);
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>{item.title || item.activityType}</Text>
      <SyncBadge state={item.syncState} />
      <Card style={styles.card}>
        <DetailRow label="Activity" value={item.activityType} />
        <DetailRow label="Time spent" value={formatDuration(item.timeSpentMinutes ?? 0)} />
        <DetailRow label="Billable" value={item.billable ? 'Yes' : 'No'} />
        <DetailRow label="Logged" value={item.createdAt?.slice(0, 10)} />
        <Pressable onPress={() => router.push({ pathname: '/cases/[caseId]', params: { caseId: item.caseId } })}>
          <Text style={styles.link}>Open case →</Text>
        </Pressable>
        {item.darzationId ? (
          <Pressable onPress={() => router.push({ pathname: '/diary/[darzationId]', params: { darzationId: item.darzationId! } })}>
            <Text style={styles.link}>Open linked diary item →</Text>
          </Pressable>
        ) : null}
      </Card>

      <Card style={styles.card}>
        <Text style={styles.cardTitle}>Summary</Text>
        <Text style={styles.body}>{item.description}</Text>
        {item.detailedNotes ? (
          <>
            <Text style={[styles.cardTitle, styles.spaced]}>Detailed notes</Text>
            <Text style={styles.body}>{item.detailedNotes}</Text>
          </>
        ) : null}
      </Card>

      <SectionHeader title="Documents" />
      {docs.length === 0 ? <Text style={styles.note}>No documents attached to this entry.</Text> : null}
      {docs.map(doc => (
        <Card key={doc.id}>
          <Pressable onPress={() => openDoc(doc)}>
            <Text style={styles.docName} numberOfLines={1}>{doc.fileName}</Text>
            <Text style={styles.note}>
              {doc.confidentiality} · v{doc.version} · {SYNC_LABEL[doc.syncStatus]}
            </Text>
          </Pressable>
          {canEdit ? <Text style={styles.remove} onPress={() => removeDoc(doc)}>Remove</Text> : null}
        </Card>
      ))}
      {canEdit ? <Button label="+ Attach documents" variant="secondary" onPress={addDocuments} /> : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.md, paddingBottom: spacing.xl * 2 },
  title: { ...typography.h1, color: colors.navy, marginBottom: spacing.sm },
  card: { marginBottom: spacing.md },
  cardTitle: { ...typography.h3, color: colors.navy, marginBottom: spacing.xs },
  spaced: { marginTop: spacing.md },
  body: { ...typography.body, color: colors.textPrimary },
  link: { ...typography.body, color: colors.navy, fontWeight: '600', marginTop: spacing.sm },
  docName: { ...typography.body, fontWeight: '600', color: colors.textPrimary },
  note: { ...typography.caption, color: colors.textSecondary, paddingVertical: spacing.xs },
  remove: { ...typography.caption, color: colors.danger, fontWeight: '600', marginTop: spacing.xs },
});
