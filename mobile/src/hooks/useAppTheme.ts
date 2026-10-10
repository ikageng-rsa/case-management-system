// Re-exported here so every cross-feature hook lives under one import path
// (@hooks/...) even though the provider/context itself lives in app/providers
// next to ThemeProvider — keeps the provider and its hook colocated while
// still being discoverable where the rest of the hooks are.
export { useAppTheme } from '@/providers/ThemeProvider';
