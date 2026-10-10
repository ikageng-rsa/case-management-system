/**
 * Expo Router derives deep links from the file tree, so the old hand-written
 * linking.ts is gone. This keeps previously-issued links working: the old
 * config exposed cases as `case/:caseId`, the routes are now `cases/:caseId`
 * (e.g. legalcms://case/123 -> /cases/123, .../case/123/activity -> /cases/123/activity).
 */
export function redirectSystemPath({ path }: { path: string; initial: boolean }) {
  try {
    return path.replace('/case/', '/cases/');
  } catch {
    return path;
  }
}
