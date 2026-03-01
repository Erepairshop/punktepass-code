/**
 * Draft Engine v2 – ColumnMathDraft
 *
 * CSS-Grid based column math scratch pad for addition / subtraction.
 *
 * Features:
 *  - Fixed cell grid (no absolute positioning, no canvas)
 *  - 1 character per cell, number-only input
 *  - Backspace moves focus left
 *  - Enter adds a new row
 *  - Arrow-key navigation
 *  - "Add line" button → CSS border-bottom on the current row
 *  - State persisted in DraftContext (keyed by testId+questionId)
 */
import React, { useCallback, useEffect, useMemo, useRef } from 'react';
import { useDraft } from './DraftProvider';
import type { ColumnMathState, GridRow, DraftKey } from './types';

/* ------------------------------------------------------------------ */
/*  Constants                                                          */
/* ------------------------------------------------------------------ */

const DEFAULT_COLUMNS = 8;
const DEFAULT_ROWS = 4;

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

function emptyRow(cols: number): GridRow {
  return {
    cells: Array.from({ length: cols }, () => ({ value: '' })),
    hasLineBelow: false,
  };
}

function createInitialState(cols: number, rows: number): ColumnMathState {
  return {
    type: 'column',
    rows: Array.from({ length: rows }, () => emptyRow(cols)),
    columnCount: cols,
    focusRow: 0,
    focusCol: 0,
  };
}

/* ------------------------------------------------------------------ */
/*  Sub-components                                                     */
/* ------------------------------------------------------------------ */

interface CellProps {
  value: string;
  rowIdx: number;
  colIdx: number;
  focused: boolean;
  onInput: (row: number, col: number, val: string) => void;
  onNav: (row: number, col: number, key: string) => void;
  registerRef: (row: number, col: number, el: HTMLInputElement | null) => void;
}

const Cell = React.memo(function Cell({
  value,
  rowIdx,
  colIdx,
  focused,
  onInput,
  onNav,
  registerRef,
}: CellProps) {
  const ref = useCallback(
    (el: HTMLInputElement | null) => registerRef(rowIdx, colIdx, el),
    [registerRef, rowIdx, colIdx],
  );

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent<HTMLInputElement>) => {
      const { key } = e;

      // Navigation keys
      if (
        key === 'ArrowUp' ||
        key === 'ArrowDown' ||
        key === 'ArrowLeft' ||
        key === 'ArrowRight' ||
        key === 'Backspace' ||
        key === 'Enter' ||
        key === 'Tab'
      ) {
        e.preventDefault();
        onNav(rowIdx, colIdx, key);
        return;
      }

      // Allow only single digits (0-9)
      if (/^[0-9]$/.test(key)) {
        e.preventDefault();
        onInput(rowIdx, colIdx, key);
        return;
      }

      // Block everything else
      e.preventDefault();
    },
    [rowIdx, colIdx, onInput, onNav],
  );

  return (
    <input
      ref={ref}
      type="text"
      inputMode="numeric"
      maxLength={1}
      value={value}
      readOnly // value set via onInput
      onKeyDown={handleKeyDown}
      autoFocus={focused}
      style={cellStyle}
      aria-label={`Row ${rowIdx + 1}, Column ${colIdx + 1}`}
    />
  );
});

/* ------------------------------------------------------------------ */
/*  Row component                                                      */
/* ------------------------------------------------------------------ */

interface RowProps {
  row: GridRow;
  rowIdx: number;
  focusRow: number;
  focusCol: number;
  columnCount: number;
  onInput: (row: number, col: number, val: string) => void;
  onNav: (row: number, col: number, key: string) => void;
  registerRef: (row: number, col: number, el: HTMLInputElement | null) => void;
}

const GridRowComponent = React.memo(function GridRowComponent({
  row,
  rowIdx,
  focusRow,
  focusCol,
  columnCount,
  onInput,
  onNav,
  registerRef,
}: RowProps) {
  return (
    <div
      style={{
        ...rowStyle,
        gridTemplateColumns: `repeat(${columnCount}, 40px)`,
        borderBottom: row.hasLineBelow ? '2px solid #333' : 'none',
      }}
    >
      {row.cells.map((cell, colIdx) => (
        <Cell
          key={colIdx}
          value={cell.value}
          rowIdx={rowIdx}
          colIdx={colIdx}
          focused={rowIdx === focusRow && colIdx === focusCol}
          onInput={onInput}
          onNav={onNav}
          registerRef={registerRef}
        />
      ))}
    </div>
  );
});

/* ------------------------------------------------------------------ */
/*  Main component                                                     */
/* ------------------------------------------------------------------ */

interface ColumnMathDraftProps {
  draftKey: DraftKey;
  columns?: number;
  initialRows?: number;
}

export const ColumnMathDraft = React.memo(function ColumnMathDraft({
  draftKey,
  columns = DEFAULT_COLUMNS,
  initialRows = DEFAULT_ROWS,
}: ColumnMathDraftProps) {
  const { getDraft, setDraft } = useDraft();

  // Initialise on first mount if no state exists
  const state: ColumnMathState = useMemo(() => {
    const existing = getDraft(draftKey);
    if (existing && existing.type === 'column') return existing;
    const init = createInitialState(columns, initialRows);
    setDraft(draftKey, init);
    return init;
  }, [draftKey, getDraft, setDraft, columns, initialRows]);

  // Ref map for focusing cells
  const cellRefs = useRef<Record<string, HTMLInputElement | null>>({});

  const registerRef = useCallback(
    (row: number, col: number, el: HTMLInputElement | null) => {
      cellRefs.current[`${row}-${col}`] = el;
    },
    [],
  );

  const focusCell = useCallback((row: number, col: number) => {
    const el = cellRefs.current[`${row}-${col}`];
    if (el) el.focus();
  }, []);

  // ---------- input handler ----------
  const handleInput = useCallback(
    (row: number, col: number, val: string) => {
      const next = structuredClone(state);
      next.rows[row].cells[col].value = val;
      // advance focus right
      const nextCol = col + 1 < next.columnCount ? col + 1 : col;
      next.focusRow = row;
      next.focusCol = nextCol;
      setDraft(draftKey, next);
      focusCell(row, nextCol);
    },
    [state, draftKey, setDraft, focusCell],
  );

  // ---------- navigation handler ----------
  const handleNav = useCallback(
    (row: number, col: number, key: string) => {
      const next = structuredClone(state);
      let r = row;
      let c = col;

      switch (key) {
        case 'ArrowLeft':
          c = Math.max(0, c - 1);
          break;
        case 'ArrowRight':
          c = Math.min(next.columnCount - 1, c + 1);
          break;
        case 'ArrowUp':
          r = Math.max(0, r - 1);
          break;
        case 'ArrowDown':
          r = Math.min(next.rows.length - 1, r + 1);
          break;
        case 'Tab':
          // Tab moves right, wraps to next row
          if (c + 1 < next.columnCount) {
            c += 1;
          } else if (r + 1 < next.rows.length) {
            r += 1;
            c = 0;
          }
          break;
        case 'Backspace':
          // Clear current cell, move left
          next.rows[r].cells[c].value = '';
          c = Math.max(0, c - 1);
          break;
        case 'Enter':
          // Add new row below current
          if (r + 1 >= next.rows.length) {
            next.rows.push(emptyRow(next.columnCount));
          }
          r = r + 1;
          c = 0;
          break;
      }

      next.focusRow = r;
      next.focusCol = c;
      setDraft(draftKey, next);
      focusCell(r, c);
    },
    [state, draftKey, setDraft, focusCell],
  );

  // ---------- add line below focused row ----------
  const addLine = useCallback(() => {
    const next = structuredClone(state);
    next.rows[next.focusRow].hasLineBelow = true;
    setDraft(draftKey, next);
  }, [state, draftKey, setDraft]);

  // ---------- add row ----------
  const addRow = useCallback(() => {
    const next = structuredClone(state);
    next.rows.push(emptyRow(next.columnCount));
    setDraft(draftKey, next);
  }, [state, draftKey, setDraft]);

  // ---------- clear ----------
  const clearDraft = useCallback(() => {
    const fresh = createInitialState(state.columnCount, DEFAULT_ROWS);
    setDraft(draftKey, fresh);
  }, [state.columnCount, draftKey, setDraft]);

  // Focus on mount / update
  useEffect(() => {
    focusCell(state.focusRow, state.focusCol);
  }, [state.focusRow, state.focusCol, focusCell]);

  return (
    <div style={containerStyle}>
      <div style={gridWrapperStyle}>
        {state.rows.map((row, rowIdx) => (
          <GridRowComponent
            key={rowIdx}
            row={row}
            rowIdx={rowIdx}
            focusRow={state.focusRow}
            focusCol={state.focusCol}
            columnCount={state.columnCount}
            onInput={handleInput}
            onNav={handleNav}
            registerRef={registerRef}
          />
        ))}
      </div>

      <div style={toolbarStyle}>
        <button type="button" onClick={addLine} style={btnStyle}>
          Vonal / Linie
        </button>
        <button type="button" onClick={addRow} style={btnStyle}>
          + Sor / Zeile
        </button>
        <button type="button" onClick={clearDraft} style={btnSecondaryStyle}>
          Törlés / Löschen
        </button>
      </div>
    </div>
  );
});

/* ------------------------------------------------------------------ */
/*  Inline styles (no absolute positioning)                            */
/* ------------------------------------------------------------------ */

const containerStyle: React.CSSProperties = {
  display: 'flex',
  flexDirection: 'column',
  gap: '8px',
  padding: '12px',
  background: '#fafafa',
  borderRadius: '8px',
  border: '1px solid #e0e0e0',
  width: 'fit-content',
};

const gridWrapperStyle: React.CSSProperties = {
  display: 'flex',
  flexDirection: 'column',
  gap: '2px',
  alignItems: 'flex-end', // right-align like real column math
};

const rowStyle: React.CSSProperties = {
  display: 'grid',
  gap: '2px',
  paddingBottom: '4px',
};

const cellStyle: React.CSSProperties = {
  width: '40px',
  height: '40px',
  textAlign: 'center',
  fontSize: '20px',
  fontFamily: 'monospace',
  border: '1px solid #ccc',
  borderRadius: '4px',
  outline: 'none',
  background: '#fff',
  caretColor: 'transparent',
  cursor: 'pointer',
};

const toolbarStyle: React.CSSProperties = {
  display: 'flex',
  gap: '8px',
  flexWrap: 'wrap',
};

const btnStyle: React.CSSProperties = {
  padding: '6px 14px',
  fontSize: '13px',
  fontWeight: 600,
  border: '1px solid #333',
  borderRadius: '6px',
  background: '#fff',
  cursor: 'pointer',
};

const btnSecondaryStyle: React.CSSProperties = {
  ...btnStyle,
  border: '1px solid #ccc',
  color: '#888',
};
