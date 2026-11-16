import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as DocumentPicker from 'expo-document-picker';
import { fetchDocumentKinds, fetchDocumentsForCase, MAX_DOCUMENT_BYTES, uploadDocument } from '@/api/endpoint/documents.api';
import { formatFileSize } from '@/api/mappers/document';
import { getApiErrorMessage } from '@/api/errors';
import Button from '@/components/Button';
import Card from '@/components/Card';
import ChipSelect from '@/components/ChipSelect';
import EmptyState from '@/components/EmptyState';
import Input from '@/components/Input';
import LoadingSpinner from '@/components/LoadingSpinner';
import { CaseDocument, DocumentKind } from '@/models/index';
import { notifyError, notifySuccess } from '@/utils/notify';
import { colors, fontFamily, spacing, typography } from '@/theme/index';

const ALL = 'all';

/** A matter's documents, newest first, with a filter by kind and an upload form. Files open through the viewer. */
export default function DocumentListScreen() {
  const router = useRouter();
  const { caseId } = useLocalSearchParams<{ caseId: string }>();
  const [items, setItems] = useState<CaseDocument[]>([]);
  const [kinds, setKinds] = useState<DocumentKind[]>([]);
  const [filter, setFilter] = useState<string>(ALL);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [picked, setPicked] = useState<DocumentPicker.DocumentPickerAsset | null>(null);
  const [uploadKind, setUploadKind] = useState<string>('');
  const [uploadName, setUploadName] = useState('');
  const [uploading, setUploading] = useState(false);

  const load = useCallback(async () => {
    try {
      const [docs, k] = await Promise.all([fetchDocumentsForCase(caseId), fetchDocumentKinds().catch(() => [] as DocumentKind[])]);
      setItems(docs);
      setKinds(k);
      setError(null);
    } catch (e) {
      setError(getApiErrorMessage(e, 'Documents could not be loaded.'));
    } finally {
      setLoading(false);
    }
  }, [caseId]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load();
  }, [load]);

  const kindName = useMemo(() => new Map(kinds.map(k => [k.id, k.name])), [kinds]);
  const visible = filter === ALL ? items : items.filter(d => d.kind === filter);

  const pick = async () => {
    const result = await DocumentPicker.getDocumentAsync({ copyToCacheDirectory: true });
    if (result.canceled) {
      return;
    }
    const asset = result.assets[0];
    if (asset.size && asset.size > MAX_DOCUMENT_BYTES) {
      notifyError('File too large', 'Documents can be up to 50 MB.');
      return;
    }
    setPicked(asset);
    setUploadName(asset.name);
    setUploadKind(prev => prev || kinds[0]?.id || '');
  };

  const upload = async () => {
    if (!picked || !uploadKind) {
      notifyError('Choose a kind', 'Pick what kind of document this is.');
      return;
    }
    setUploading(true);
    try {
      await uploadDocument(caseId, { uri: picked.uri, name: picked.name, type: picked.mimeType ?? 'application/octet-stream' }, uploadKind, uploadName);
      notifySuccess('Document uploaded', picked.name);
      setPicked(null);
      await load();
    } catch (e) {
      notifyError('Upload failed', getApiErrorMessage(e, 'The document could not be uploaded.'));
    } finally {
      setUploading(false);
    }
  };

  if (loading) {
    return <LoadingSpinner />;
  }

  const header = (
    <View>
      {kinds.length > 0 ? (
        <ChipSelect
          label="Kind"
          options={[{ value: ALL, label: 'All' }, ...kinds.map(k => ({ value: k.id, label: k.name }))]}
          selected={[filter]}
          onChange={next => setFilter(next[0] ?? ALL)}
        />
      ) : null}
      {picked ? (
        <Card>
          <Text style={styles.fileName} numberOfLines={1}>{picked.name}</Text>
          <Input label="Listed as" value={uploadName} onChangeText={setUploadName} maxLength={255} />
          <ChipSelect label="Kind" options={kinds.map(k => ({ value: k.id, label: k.name }))} selected={[uploadKind]} onChange={next => setUploadKind(next[0] ?? '')} />
          <Button label="Upload" onPress={upload} loading={uploading} />
          <Button label="Cancel" variant="secondary" onPress={() => setPicked(null)} disabled={uploading} style={styles.gap} />
        </Card>
      ) : (
        <Button label="+ Upload a document" variant="secondary" onPress={pick} style={styles.gap} />
      )}
    </View>
  );

  return (
    <View style={styles.container}>
      <FlatList
        data={visible}
        keyExtractor={item => item.id}
        contentContainerStyle={styles.list}
        ListHeaderComponent={header}
        renderItem={({ item }) => (
          <Pressable
            accessibilityRole="button"
            onPress={() => router.push({ pathname: '/cases/[caseId]/documents/[documentId]', params: { caseId, documentId: item.id } })}
          >
            <Card>
              <View style={styles.row}>
                <Text style={styles.fileName} numberOfLines={1}>{item.name}</Text>
                <Text style={styles.badge}>{kindName.get(item.kind) ?? item.kind}</Text>
              </View>
              <Text style={styles.meta}>
                {[item.fileName !== item.name ? item.fileName : null, formatFileSize(item.sizeBytes), item.uploadedAt.slice(0, 10)].filter(Boolean).join(' · ')}
              </Text>
            </Card>
          </Pressable>
        )}
        ListEmptyComponent={
          <EmptyState title={error ? 'Not available' : 'No documents'} message={error ?? 'Upload the first file for this matter.'} />
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  list: { padding: spacing.md },
  gap: { marginTop: spacing.sm },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  fileName: { ...typography.body, color: colors.textPrimary, fontFamily: fontFamily.sansSemibold, flex: 1, marginRight: spacing.sm },
  badge: { ...typography.caption, color: colors.goldText, fontFamily: fontFamily.sansSemibold },
  meta: { ...typography.caption, color: colors.textSecondary, marginTop: spacing.xs },
});
