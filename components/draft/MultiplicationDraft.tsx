/**
 * Draft Engine v2 – MultiplicationDraft
 *
 * CSS Grid based long multiplication scratch pad.
 *
 * Layout (German "schriftliches Multiplizieren"):
 *   ┌─────────────────┐
 *   │   multiplicand   │  row 0
 *   │ × multiplier     │  row 1
 *   │ ────────────     │  line
 *   │   partial 1      │  row 2  (multiplicand × last digit)
 *   │   partial 2      │  row 3  (multiplicand × 2nd digit, shifted)
 *   │   ...            │
 *   │ ────────────     │  line
 *   │   result         │  final row
 *   └─────────────────┘
 *
 * Features:
 *  - Same cell-grid system as ColumnMathDraft
 *  - No canvas, no absolute positioning
 *  - Add line / add row controls
 *  - Arrow key + Backspace + Enter navigation
 */
import React, { useCallback, useEffect, useMemo, useRef } from 'react';
import { useDraft } from './DraftProvider';
import type { MultiplicationState, GridRow, DraftKey } from './types';

/* ------------------------------------------------------------------ */
/*  Constants                                                          */
/* ------------------------------------------------------------------ */

const DEFAULT_COLUMNS = 10;
const DEFAULT_ROWS = 5; // multiplicand + multiplier + line + partial + result

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

function emptyRow(cols: number): GridRow {
  return {
    cells: Array.from({ length: cols }, () => ({ value: '' })),
    hasLineBelow: false,
  };
}

function createInitialState(cols: number): MultiplicationState {
  const rows: GridRow[] = [];
  // Row 0: multiplicand
  rows.push(emptyRow(cols));
  // Row 1: multiplier (has line below by default)
  const multiplierRow = emptyRow(cols);
  multiplierRow.hasLineBelow = true;
  rows.push(multiplierRow);
  // Rows 2-3: partial products
  rows.push(emptyRow(cols));
  rows.push(emptyRow(cols));
  // Row 4: result (line will be added by user when ready)
  rows.push(emptyRow(cols));

  return {
    type: 'multiplication',
    rows,
    columnCount: cols,
    focusRow: 0,
    focusCol: cols - 1, // start right-most (standard for multiplication)
  };
}

/* ------------------------------------------------------------------ */
/*  Cell                                                               */
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
      if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Backspace', 'Enter', 'Tab'].includes(key)) {
        e.preventDefault();
        onNav(rowIdx, colIdx, key);
        return;
      }
      if (/^[0-9]$/.test(key)) {
        e.preventDefault();
        onInput(rowIdx, colIdx, key);
        return;
      }
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
      readOnly
      onKeyDown={handleKeyDown}
      autoFocus={focused}
      style={cellStyle}
      aria-label={`Row ${rowIdx + 1}, Column ${colIdx + 1}`}
    />
  );
});

/* ------------------------------------------------------------------ */
/*  Row                                                                */
/* ------------------------------------------------------------------ */

interface RowProps {
  row: GridRow;
  rowIdx: number;
  focusRow: number;
  focusCol: number;
  columnCount: number;
  label?: string;
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
  label,
  onInput,
  onNav,
  registerRef,
}: RowProps) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
      {label && <span style={rowLabelStyle}>{label}</span>}
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: `repeat(${columnCount}, 36px)`,
          gap: '2px',
          paddingBottom: '4px',
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
    </div>
  );
});

/* ------------------------------------------------------------------ */
/*  Main component                                                     */
/* ------------------------------------------------------------------ */

interface MultiplicationDraftProps {
  draftKey: DraftKey;
  columns?: number;
}

export const MultiplicationDraft = React.memo(function MultiplicationDraft({
  draftKey,
  columns = DEFAULT_COLUMNS,
}: MultiplicationDraftProps) {
  const { getDraft, setDraft } = useDraft();

  const state: MultiplicationState = useMemo(() => {
    const existing = getDraft(draftKey);
    if (existing && existing.type === 'multiplication') return existing;
    const init = createInitialState(columns);
    setDraft(draftKey, init);
    return init;
  }, [draftKey, getDraft, setDraft, columns]);

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

  // ---------- input ----------
  const handleInput = useCallback(
    (row: number, col: number, val: string) => {
      const next = structuredClone(state);
      next.rows[row].cells[col].value = val;
      // Move focus left (multiplication fills right-to-left)
      const nextCol = col - 1 >= 0 ? col - 1 : col;
      next.focusRow = row;
      next.focusCol = nextCol;
      setDraft(draftKey, next);
      focusCell(row, nextCol);
    },
    [state, draftKey, setDraft, focusCell],
  );

  // ---------- navigation ----------
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
          // Tab moves left (right-to-left entry), wraps to next row
          if (c - 1 >= 0) {
            c -= 1;
          } else if (r + 1 < next.rows.length) {
            r += 1;
            c = next.columnCount - 1;
          }
          break;
        case 'Backspace':
          next.rows[r].cells[c].value = '';
          c = Math.min(next.columnCount - 1, c + 1); // move right (undo direction)
          break;
        case 'Enter':
          if (r + 1 >= next.rows.length) {
            next.rows.push(emptyRow(next.columnCount));
          }
          r += 1;
          c = next.columnCount - 1; // start from right
          break;
      }

      next.focusRow = r;
      next.focusCol = c;
      setDraft(draftKey, next);
      focusCell(r, c);
    },
    [state, draftKey, setDraft, focusCell],
  );

  // ---------- toolbar ----------
  const addLine = useCallback(() => {
    const next = structuredClone(state);
    next.rows[next.focusRow].hasLineBelow = true;
    setDraft(draftKey, next);
  }, [state, draftKey, setDraft]);

  const addRow = useCallback(() => {
    const next = structuredClone(state);
    next.rows.push(emptyRow(next.columnCount));
    setDraft(draftKey, next);
  }, [state, draftKey, setDraft]);

  const clearDraft = useCallback(() => {
    setDraft(draftKey, createInitialState(state.columnCount));
  }, [state.columnCount, draftKey, setDraft]);

  useEffect(() => {
    focusCell(state.focusRow, state.focusCol);
  }, [state.focusRow, state.focusCol, focusCell]);

  // Row labels
  const rowLabel = (idx: number): string | undefined => {
    if (idx === 1) return '×';
    return undefined;
  };

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
            label={rowLabel(rowIdx)}
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
/*  Styles                                                             */
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
  alignItems: 'flex-end',
};

const rowLabelStyle: React.CSSProperties = {
  fontSize: '18px',
  fontWeight: 700,
  fontFamily: 'monospace',
  width: '20px',
  textAlign: 'center',
  color: '#555',
};

const cellStyle: React.CSSProperties = {
  width: '36px',
  height: '36px',
  textAlign: 'center',
  fontSize: '18px',
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
