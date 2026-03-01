/**
 * Draft Engine v2 – Public API
 *
 * Usage:
 *   import {
 *     DraftProvider,
 *     useDraft,
 *     ColumnMathDraft,
 *     DivisionDraft,
 *     MultiplicationDraft,
 *     FreeDraftCanvas,
 *     makeDraftKey,
 *   } from '@/components/draft';
 */

// Context & Provider
export { DraftProvider, useDraft } from './DraftProvider';

// Grid-based draft components
export { ColumnMathDraft } from './ColumnMathDraft';
export { DivisionDraft } from './DivisionDraft';
export { MultiplicationDraft } from './MultiplicationDraft';

// Fallback canvas
export { FreeDraftCanvas } from './FreeDraftCanvas';

// Types & helpers
export { makeDraftKey } from './types';
export type {
  DraftKey,
  DraftState,
  ColumnMathState,
  DivisionState,
  MultiplicationState,
  FreeDraftState,
  GridRow,
  CellValue,
  ColumnOperation,
} from './types';
