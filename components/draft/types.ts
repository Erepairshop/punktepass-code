/** Draft Engine v2 – Shared Types */

/** Single cell in a grid row */
export interface CellValue {
  value: string; // single digit or empty
}

/** A row of cells in column math or division */
export interface GridRow {
  cells: CellValue[];
  hasLineBelow: boolean; // CSS border-bottom separator line
}

/** Operation types supported by ColumnMathDraft */
export type ColumnOperation = 'addition' | 'subtraction';

/** State for a single ColumnMath draft instance */
export interface ColumnMathState {
  type: 'column';
  rows: GridRow[];
  columnCount: number;
  focusRow: number;
  focusCol: number;
}

/** State for a single Division draft instance */
export interface DivisionState {
  type: 'division';
  dividend: GridRow;    // top row: the number being divided
  divisor: string;      // the divisor (displayed left of dividend)
  quotient: GridRow;    // answer row above
  workRows: GridRow[];  // intermediate subtraction rows
  columnCount: number;
  focusSection: 'quotient' | 'dividend' | 'work';
  focusRow: number;
  focusCol: number;
}

/** State for a single Multiplication draft instance */
export interface MultiplicationState {
  type: 'multiplication';
  rows: GridRow[];       // operand rows + partial products
  columnCount: number;
  focusRow: number;
  focusCol: number;
}

/** State for the freehand canvas fallback */
export interface FreeDraftState {
  type: 'free';
  paths: { x: number; y: number }[][]; // array of stroke paths
  canvasWidth: number;
  canvasHeight: number;
}

/** Union of all draft states */
export type DraftState =
  | ColumnMathState
  | DivisionState
  | MultiplicationState
  | FreeDraftState;

/** Unique key for each draft: testId-questionId */
export type DraftKey = string;

/** Build a DraftKey from testId + questionId */
export function makeDraftKey(testId: string, questionId: string): DraftKey {
  return `${testId}-${questionId}`;
}
