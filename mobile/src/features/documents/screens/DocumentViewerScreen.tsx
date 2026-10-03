import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useRoute } from '@react-navigation/native';
import { colors, spacing, typography } from '@/theme/index';

// A real implementation streams the document (e.g. via a signed URL into
// react-native-pdf or a WebView) rather than downloading it outright —
// especially for Restricted/Privileged files, per the offlineCache policy.
export default function DocumentViewerScreen() {
  const route = useRoute<any>();
  const { documentId } = route.params as { documentId: string };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Document {documentId}</Text>
      <Text style={styles.note}>
        Viewer placeholder — wire up react-native-pdf or a WebView pointed at a short-lived signed URL from
        the backend.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, padding: spacing.lg },
  title: { ...typography.h2, color: colors.navy, marginBottom: spacing.sm },
  note: { ...typography.body, color: colors.textSecondary },
});
