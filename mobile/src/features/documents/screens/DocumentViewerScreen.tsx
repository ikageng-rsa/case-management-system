import React, { useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import * as Sharing from 'expo-sharing';
import { discardDownload, downloadDocument, fetchDocumentsForCase } from '@/api/endpoint/documents.api';
import { formatFileSize } from '@/api/mappers/document';
import { getApiErrorMessage } from '@/api/errors';
import Button from '@/components/Button';
import LoadingSpinner from '@/components/LoadingSpinner';
import { CaseDocument } from '@/models/index';
import { notifyError } from '@/utils/notify';
import { colors, spacing, typography } from '@/theme/index';

/**
 * Shows a document's details and opens the file in whatever app the person picks. The file is downloaded with the
 * session token, handed to the share sheet and deleted from the phone as soon as the sheet closes.
 */
export default function DocumentViewerScreen() {
  const { caseId, documentId } = useLocalSearchParams<{ caseId: string; documentId: string }>();
  const [doc, setDoc] = useState<CaseDocument | null>(null);
  const [loading, setLoading] = useState(true);
  const [opening, setOpening] = useState(false);

  useEffect(() => {
    fetchDocumentsForCase(caseId)
      .then(list => setDoc(list.find(d => d.id === documentId) ?? null))
      .catch(() => setDoc(null))
      .finally(() => setLoading(false));
  }, [caseId, documentId]);

  const open = async () => {
    if (!doc) {
      return;
    }
    setOpening(true);
    let uri: string | null = null;
    try {
      if (!(await Sharing.isAvailableAsync())) {
        notifyError('Cannot open', 'Opening files is not available on this device.');
        return;
      }
      uri = await downloadDocument(caseId, doc);
      await Sharing.shareAsync(uri, { mimeType: doc.mimeType, dialogTitle: doc.name });
    } catch (e) {
      notifyError('Could not open the document', getApiErrorMessage(e, 'The download failed.'));
    } finally {
      if (uri) {
        await discardDownload(uri);
      }
      setOpening(false);
    }
  };

  if (loading) {
    return <LoadingSpinner />;
  }
  if (!doc) {
    return (
      <View style={styles.container}>
        <Text style={styles.note}>This document is not available.</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{doc.name}</Text>
      <Text style={styles.note}>{[doc.fileName, formatFileSize(doc.sizeBytes), doc.mimeType].filter(Boolean).join(' · ')}</Text>
      <Text style={styles.note}>Added {doc.uploadedAt.slice(0, 10)}</Text>
      <Text style={styles.note}>Each download is recorded in the audit trail of the matter.</Text>
      <Button label="Open file" onPress={open} loading={opening} style={styles.button} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, padding: spacing.lg },
  title: { ...typography.h2, color: colors.navy, marginBottom: spacing.sm },
  note: { ...typography.body, color: colors.textSecondary, marginBottom: spacing.xs },
  button: { marginTop: spacing.lg },
});
