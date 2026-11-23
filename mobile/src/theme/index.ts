/**
 * Single import surface for the whole design system:
 *   import { colors, spacing, radius, typography, shadows } from '@theme/index';
 * Existing screens already import this way — this file just composes the
 * split-out token files so nothing downstream needs to change.
 * The palette itself comes from the shared UI kit (src/ui).
 */
import { colors } from './colors';
import { spacing, radius } from './spacing';
import { typography, fontFamily } from './typography';
import { shadows } from './shadows';

export { colors };
export type { AppColors } from './colors';

export { spacing, radius };

export { typography, fontFamily };
export type { AppTypography } from './typography';

export { shadows };

const theme = { colors, spacing, radius, typography, fontFamily, shadows };
export type AppTheme = typeof theme;
export default theme;
