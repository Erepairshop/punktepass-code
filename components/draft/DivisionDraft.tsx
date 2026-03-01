/**
 * Draft Engine v2 – DivisionDraft
 *
 * Stable grid-based long division layout.
 *
 * Layout (German "schriftliches Dividieren"):
 *   ┌──────────────────────────┐
 *   │  dividend  :  divisor  = quotient │
 *   ├──────────────────────────┤
 *   │  work row 1 (subtract)           │
 *   │  ─────── line                    │
 *   │  work row 2 (bring down)         │
 *   │  work row 3 (subtract)           │
 *   │  ─────── line                    │
 *   │  ...                             │
 *   └──────────────────────────┘
 *
 * Features:
 *  - Separate grid containers for each section
 *  - Fixed column template (no auto-reposition)
 *  - Deterministic layout – no scroll/resize jitter
 *  - Arrow key / Backspace / Enter navigation
 *  - "Add line" via CSS border-bottom
 */
import React, { useCallback, useEffect, useRef, useMemo } from 'react';
import { useDraft } from './DraftProvider';
import type { DivisionState, GridRow, DraftKey, CellValue } from './types';

/* ------------------------------------------------------------------ */
/*  Constants                                                          */
/* ------------------------------------------------------------------ */

const DEFAULT_COLUMNS = 10;
const DEFAULT_WORK_ROWS = 4;

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

function emptyRow(cols: number): GridRow {
  return {
    cells: Array.from({ length: cols }, () => ({ value: '' })),
    hasLineBelow: false,
  };
}

function createInitialState(cols: number): DivisionState {
  return {
    type: 'division',
    dividend: emptyRow(cols),
    divisor: '',
    quotient: emptyRow(cols),
    workRows: Array.from({ length: DEFAULT_WORK_ROWS }, () => emptyRow(cols)),
    columnCount: cols,
    focusSection: 'dividend',
    focusRow: 0,
    focusCol: 0,
  };
}

/* ------------------------------------------------------------------ */
/*  Cell                                                               */
/* ------------------------------------------------------------------ */

interface CellProps {
  value: string;
  section: 'quotient' | 'dividend' | 'work';
  rowIdx: number;
  colIdx: number;
  focused: boolean;
  onInput: (section: string, row: number, col: number, val: string) => void;
  onNav: (section: string, row: number, col: number, key: string) => void;
  registerRef: (section: string, row: number, col: number, el: HTMLInputElement | null) => void;
}

const Cell = React.memo(function Cell({
  value,
  section,
  rowIdx,
  colIdx,
  focused,
  onInput,
  onNav,
  registerRef,
}: CellProps) {
  const ref = useCallback(
    (el: HTMLInputElement | null) => registerRef(section, rowIdx, colIdx, el),
    [registerRef, section, rowIdx, colIdx],
  );

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent<HTMLInputElement>) => {
      const { key } = e;
      if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Backspace', 'Enter', 'Tab'].includes(key)) {
        e.preventDefault();
        onNav(section, rowIdx, colIdx, key);
        return;
      }
      if (/^[0-9]$/.test(key)) {
        e.preventDefault();
        onInput(section, rowIdx, colIdx, key);
        return;
      }
      e.preventDefault();
    },
    [section, rowIdx, colIdx, onInput, onNav],
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
      aria-label={`${section} row ${rowIdx + 1}, col ${colIdx + 1}`}
    />
  );
});

/* ------------------------------------------------------------------ */
/*  GridRow renderer                                                   */
/* ------------------------------------------------------------------ */

interface RowDisplayProps {
  row: GridRow;
  section: 'quotient' | 'dividend' | 'work';
  rowIdx: number;
  columnCount: number;
  focusSection: string;
  focusRow: number;
  focusCol: number;
  onInput: (section: string, row: number, col: number, val: string) => void;
  onNav: (section: string, row: number, col: number, key: string) => void;
  registerRef: (section: string, row: number, col: number, el: HTMLInputElement | null) => void;
}

const RowDisplay = React.memo(function RowDisplay({
  row,
  section,
  rowIdx,
  columnCount,
  focusSection,
  focusRow,
  focusCol,
  onInput,
  onNav,
  registerRef,
}: RowDisplayProps) {
  return (
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
          section={section}
          rowIdx={rowIdx}
          colIdx={colIdx}
          focused={focusSection === section && focusRow === rowIdx && focusCol === colIdx}
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

interface DivisionDraftProps {
  draftKey: DraftKey;
  columns?: number;
}

export const DivisionDraft = React.memo(function DivisionDraft({
  draftKey,
  columns = DEFAULT_COLUMNS,
}: DivisionDraftProps) {
  const { getDraft, setDraft } = useDraft();

  const state: DivisionState = useMemo(() => {
    const existing = getDraft(draftKey);
    if (existing && existing.type === 'division') return existing;
    const init = createInitialState(columns);
    setDraft(draftKey, init);
    return init;
  }, [draftKey, getDraft, setDraft, columns]);

  const cellRefs = useRef<Record<string, HTMLInputElement | null>>({});

  const registerRef = useCallback(
    (section: string, row: number, col: number, el: HTMLInputElement | null) => {
      cellRefs.current[`${section}-${row}-${col}`] = el;
    },
    [],
  );

  const focusCellEl = useCallback((section: string, row: number, col: number) => {
    const el = cellRefs.current[`${section}-${row}-${col}`];
    if (el) el.focus();
  }, []);

  // ---------- which row array for a given section ----------
  const getRows = useCallback(
    (section: string, st: DivisionState): GridRow[] => {
      if (section === 'quotient') return [st.quotient];
      if (section === 'dividend') return [st.dividend];
      return st.workRows;
    },
    [],
  );

  // ---------- input ----------
  const handleInput = useCallback(
    (section: string, row: number, col: number, val: string) => {
      const next = structuredClone(state);
      if (section === 'quotient') {
        next.quotient.cells[col].value = val;
      } else if (section === 'dividend') {
        next.dividend.cells[col].value = val;
      } else {
        next.workRows[row].cells[col].value = val;
      }
      const nextCol = col + 1 < next.columnCount ? col + 1 : col;
      next.focusSection = section as DivisionState['focusSection'];
      next.focusRow = row;
      next.focusCol = nextCol;
      setDraft(draftKey, next);
      focusCellEl(section, row, nextCol);
    },
    [state, draftKey, setDraft, focusCellEl],
  );

  // ---------- navigation ----------
  const handleNav = useCallback(
    (section: string, row: number, col: number, key: string) => {
      const next = structuredClone(state);
      let sec = section;
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
          if (r > 0) {
            r -= 1;
          } else {
            // Move to section above
            if (sec === 'work') { sec = 'dividend'; r = 0; }
            else if (sec === 'dividend') { sec = 'quotient'; r = 0; }
          }
          break;
        case 'ArrowDown':
          if (sec === 'quotient') {
            sec = 'dividend'; r = 0;
          } else if (sec === 'dividend') {
            sec = 'work'; r = 0;
          } else if (r + 1 < next.workRows.length) {
            r += 1;
          }
          break;
        case 'Tab':
          if (c + 1 < next.columnCount) {
            c += 1;
          } else {
            c = 0;
            // move to next section / row
            if (sec === 'quotient') { sec = 'dividend'; r = 0; }
            else if (sec === 'dividend') { sec = 'work'; r = 0; }
            else if (r + 1 < next.workRows.length) { r += 1; }
          }
          break;
        case 'Backspace':
          if (sec === 'quotient') next.quotient.cells[c].value = '';
          else if (sec === 'dividend') next.dividend.cells[c].value = '';
          else next.workRows[r].cells[c].value = '';
          c = Math.max(0, c - 1);
          break;
        case 'Enter':
          // In work section, go to next row or add one
          if (sec === 'work') {
            if (r + 1 >= next.workRows.length) {
              next.workRows.push(emptyRow(next.columnCount));
            }
            r += 1;
            c = 0;
          } else if (sec === 'quotient') {
            sec = 'dividend'; r = 0; c = 0;
          } else {
            sec = 'work'; r = 0; c = 0;
          }
          break;
      }

      next.focusSection = sec as DivisionState['focusSection'];
      next.focusRow = r;
      next.focusCol = c;
      setDraft(draftKey, next);
      focusCellEl(sec, r, c);
    },
    [state, draftKey, setDraft, focusCellEl],
  );

  // ---------- divisor input (separate text field) ----------
  const handleDivisorChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const val = e.target.value.replace(/[^0-9]/g, '');
      const next = structuredClone(state);
      next.divisor = val;
      setDraft(draftKey, next);
    },
    [state, draftKey, setDraft],
  );

  // ---------- toolbar actions ----------
  const addLine = useCallback(() => {
    const next = structuredClone(state);
    if (next.focusSection === 'work') {
      next.workRows[next.focusRow].hasLineBelow = true;
    } else if (next.focusSection === 'dividend') {
      next.dividend.hasLineBelow = true;
    }
    setDraft(draftKey, next);
  }, [state, draftKey, setDraft]);

  const addWorkRow = useCallback(() => {
    const next = structuredClone(state);
    next.workRows.push(emptyRow(next.columnCount));
    setDraft(draftKey, next);
  }, [state, draftKey, setDraft]);

  const clearDraft = useCallback(() => {
    setDraft(draftKey, createInitialState(state.columnCount));
  }, [state.columnCount, draftKey, setDraft]);

  // Focus on mount
  useEffect(() => {
    focusCellEl(state.focusSection, state.focusRow, state.focusCol);
  }, [state.focusSection, state.focusRow, state.focusCol, focusCellEl]);

  return (
    <div style={containerStyle}>
      {/* Quotient row (answer) */}
      <div style={sectionStyle}>
        <span style={labelStyle}>= Eredmény / Ergebnis</span>
        <RowDisplay
          row={state.quotient}
          section="quotient"
          rowIdx={0}
          columnCount={state.columnCount}
          focusSection={state.focusSection}
          focusRow={state.focusRow}
          focusCol={state.focusCol}
          onInput={handleInput}
          onNav={handleNav}
          registerRef={registerRef}
        />
      </div>

      {/* Dividend : Divisor */}
      <div style={dividendSectionStyle}>
        <div style={{ flex: 1 }}>
          <RowDisplay
            row={state.dividend}
            section="dividend"
            rowIdx={0}
            columnCount={state.columnCount}
            focusSection={state.focusSection}
            focusRow={state.focusRow}
            focusCol={state.focusCol}
            onInput={handleInput}
            onNav={handleNav}
            registerRef={registerRef}
          />
        </div>
        <div style={divisorWrapperStyle}>
          <span style={{ fontSize: '20px', fontWeight: 700 }}>:</span>
          <input
            type="text"
            inputMode="numeric"
            value={state.divisor}
            onChange={handleDivisorChange}
            placeholder="÷"
            style={divisorInputStyle}
            aria-label="Divisor"
          />
        </div>
      </div>

      {/* Work rows (intermediate steps) */}
      <div style={sectionStyle}>
        <span style={labelStyle}>Munka / Nebenrechnung</span>
        {state.workRows.map((row, idx) => (
          <RowDisplay
            key={idx}
            row={row}
            section="work"
            rowIdx={idx}
            columnCount={state.columnCount}
            focusSection={state.focusSection}
            focusRow={state.focusRow}
            focusCol={state.focusCol}
            onInput={handleInput}
            onNav={handleNav}
            registerRef={registerRef}
          />
        ))}
      </div>

      {/* Toolbar */}
      <div style={toolbarStyle}>
        <button type="button" onClick={addLine} style={btnStyle}>
          Vonal / Linie
        </button>
        <button type="button" onClick={addWorkRow} style={btnStyle}>
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
  gap: '12px',
  padding: '12px',
  background: '#fafafa',
  borderRadius: '8px',
  border: '1px solid #e0e0e0',
  width: 'fit-content',
};

const sectionStyle: React.CSSProperties = {
  display: 'flex',
  flexDirection: 'column',
  gap: '2px',
  alignItems: 'flex-end',
};

const dividendSectionStyle: React.CSSProperties = {
  display: 'flex',
  alignItems: 'center',
  gap: '12px',
};

const divisorWrapperStyle: React.CSSProperties = {
  display: 'flex',
  alignItems: 'center',
  gap: '6px',
};

const divisorInputStyle: React.CSSProperties = {
  width: '60px',
  height: '36px',
  textAlign: 'center',
  fontSize: '18px',
  fontFamily: 'monospace',
  border: '1px solid #ccc',
  borderRadius: '4px',
  outline: 'none',
};

const labelStyle: React.CSSProperties = {
  fontSize: '11px',
  color: '#999',
  letterSpacing: '0.5px',
  textTransform: 'uppercase',
  alignSelf: 'flex-start',
  marginBottom: '2px',
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
