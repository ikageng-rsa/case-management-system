import { Platform } from 'react-native';
import { deviceAuthAvailable, requireDeviceAuth } from '@/features/auth/services/deviceAuth';
import { exportFileName, ReportKind, ReportTable, toCsv, toHtml } from './reportBuilder';

export type ExportFormat = 'csv' | 'pdf';

export class ExportCancelledError extends Error {
    constructor() {
        super('Export cancelled.');
        this.name = 'ExportCancelledError';
    }
}

/** Reports hold client details: confirm it's really the phone's owner when the phone can check. */
async function confirmOwner(): Promise<void> {
    if (await deviceAuthAvailable()) {
        if (!(await requireDeviceAuth('Confirm it is you to export client data'))) {
            throw new ExportCancelledError();
        }
    }
}

function downloadOnWeb(name: string, mime: string, content: string): void {
    const url = URL.createObjectURL(new Blob([content], { type: mime }));
    const a = document.createElement('a');
    a.href = url;
    a.download = name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

/**
 * Builds the file on the phone (nothing is sent to a third party) and opens the system share sheet so the
 * user chooses where it goes: e-mail, WhatsApp, Drive, "Save to Files"...
 */
export async function exportReport(kind: ReportKind, table: ReportTable, format: ExportFormat): Promise<string> {
    await confirmOwner();
    const name = exportFileName(kind, format);

    if (Platform.OS === 'web') {
        if (format === 'csv') {
            downloadOnWeb(name, 'text/csv;charset=utf-8', toCsv(table));
        } else {
            const frame = window.open('', '_blank');
            if (frame) {
                frame.document.write(toHtml(table));
                frame.document.close();
                frame.print();
            }
        }
        return name;
    }

    // Native modules are loaded here, not at start-up, so a build without one can't stop the app opening.
    const FileSystem = await import('expo-file-system/legacy');
    const Sharing = await import('expo-sharing');
    if (!(await Sharing.isAvailableAsync())) {
        throw new Error('Sharing is not available on this device.');
    }
    await clearExports();

    let uri: string;
    if (format === 'csv') {
        uri = `${FileSystem.cacheDirectory}${name}`;
        await FileSystem.writeAsStringAsync(uri, toCsv(table), { encoding: FileSystem.EncodingType.UTF8 });
    } else {
        const Print = await import('expo-print');
        const printed = await Print.printToFileAsync({ html: toHtml(table) });
        // Give the PDF a readable name.
        uri = `${FileSystem.cacheDirectory}${name}`;
        await FileSystem.moveAsync({ from: printed.uri, to: uri });
    }
    await Sharing.shareAsync(uri, {
        mimeType: format === 'csv' ? 'text/csv' : 'application/pdf',
        dialogTitle: table.title,
        UTI: format === 'csv' ? 'public.comma-separated-values-text' : 'com.adobe.pdf',
    });
    return name;
}

/** Removes earlier exports from the phone's cache (called before each export and at sign-out). */
export async function clearExports(): Promise<void> {
    if (Platform.OS === 'web') {
        return;
    }
    try {
        const FileSystem = await import('expo-file-system/legacy');
        const dir = FileSystem.cacheDirectory;
        if (!dir) {
            return;
        }
        const files = await FileSystem.readDirectoryAsync(dir);
        await Promise.all(
            files.filter(f => f.startsWith('legalcms-') && /\.(csv|pdf)$/.test(f)).map(f => FileSystem.deleteAsync(`${dir}${f}`, { idempotent: true })),
        );
    } catch {
        // best effort
    }
}
