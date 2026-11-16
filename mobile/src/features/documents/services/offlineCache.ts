/**
 * Document files are never kept on the phone after they have been opened: `downloadDocument` writes to the cache
 * folder and the caller deletes the copy straight after sharing it. Only the document list (names, kinds, sizes)
 * is cached, by `fetchDocumentsForCase`, so the list still shows offline. Files attached on this phone and not
 * yet uploaded live in the app's own storage until they reach the server (see narrationDocuments).
 */
export const DOCUMENT_FILES_CACHED_ON_DEVICE = false;
