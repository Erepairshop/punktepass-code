/**
 * Draft Engine v2 – FreeDraftCanvas
 *
 * Lightweight freehand drawing fallback.
 * Uses plain <canvas> + pointer events – no external library.
 *
 * This is an OPTIONAL fallback component, not the primary draft tool.
 * Use ColumnMathDraft / DivisionDraft / MultiplicationDraft for structured work.
 */
import React, { useCallback, useEffect, useRef, useMemo } from 'react';
import { useDraft } from './DraftProvider';
import type { FreeDraftState, DraftKey } from './types';

/* ------------------------------------------------------------------ */
/*  Constants                                                          */
/* ------------------------------------------------------------------ */

const DEFAULT_WIDTH = 360;
const DEFAULT_HEIGHT = 260;
const STROKE_COLOR = '#333';
const STROKE_WIDTH = 2;

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

function createInitialState(w: number, h: number): FreeDraftState {
  return {
    type: 'free',
    paths: [],
    canvasWidth: w,
    canvasHeight: h,
  };
}

/** Redraw all paths onto a canvas context */
function redraw(
  ctx: CanvasRenderingContext2D,
  paths: { x: number; y: number }[][],
  width: number,
  height: number,
) {
  ctx.clearRect(0, 0, width, height);
  ctx.strokeStyle = STROKE_COLOR;
  ctx.lineWidth = STROKE_WIDTH;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';

  for (const path of paths) {
    if (path.length < 2) continue;
    ctx.beginPath();
    ctx.moveTo(path[0].x, path[0].y);
    for (let i = 1; i < path.length; i++) {
      ctx.lineTo(path[i].x, path[i].y);
    }
    ctx.stroke();
  }
}

/* ------------------------------------------------------------------ */
/*  Main component                                                     */
/* ------------------------------------------------------------------ */

interface FreeDraftCanvasProps {
  draftKey: DraftKey;
  width?: number;
  height?: number;
}

export const FreeDraftCanvas = React.memo(function FreeDraftCanvas({
  draftKey,
  width = DEFAULT_WIDTH,
  height = DEFAULT_HEIGHT,
}: FreeDraftCanvasProps) {
  const { getDraft, setDraft } = useDraft();

  const state: FreeDraftState = useMemo(() => {
    const existing = getDraft(draftKey);
    if (existing && existing.type === 'free') return existing;
    const init = createInitialState(width, height);
    setDraft(draftKey, init);
    return init;
  }, [draftKey, getDraft, setDraft, width, height]);

  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const isDrawing = useRef(false);
  const currentPath = useRef<{ x: number; y: number }[]>([]);

  // Redraw when state changes (e.g. undo)
  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    redraw(ctx, state.paths, state.canvasWidth, state.canvasHeight);
  }, [state]);

  // ---------- pointer event helpers ----------
  const getPos = useCallback(
    (e: React.PointerEvent<HTMLCanvasElement>): { x: number; y: number } => {
      const rect = canvasRef.current!.getBoundingClientRect();
      return {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top,
      };
    },
    [],
  );

  const handlePointerDown = useCallback(
    (e: React.PointerEvent<HTMLCanvasElement>) => {
      isDrawing.current = true;
      currentPath.current = [getPos(e)];
      canvasRef.current?.setPointerCapture(e.pointerId);
    },
    [getPos],
  );

  const handlePointerMove = useCallback(
    (e: React.PointerEvent<HTMLCanvasElement>) => {
      if (!isDrawing.current) return;
      const pos = getPos(e);
      currentPath.current.push(pos);

      // Draw live stroke
      const canvas = canvasRef.current;
      if (!canvas) return;
      const ctx = canvas.getContext('2d');
      if (!ctx) return;
      const path = currentPath.current;
      if (path.length < 2) return;
      ctx.strokeStyle = STROKE_COLOR;
      ctx.lineWidth = STROKE_WIDTH;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.beginPath();
      ctx.moveTo(path[path.length - 2].x, path[path.length - 2].y);
      ctx.lineTo(pos.x, pos.y);
      ctx.stroke();
    },
    [getPos],
  );

  const handlePointerUp = useCallback(() => {
    if (!isDrawing.current) return;
    isDrawing.current = false;
    if (currentPath.current.length > 1) {
      const next: FreeDraftState = {
        ...state,
        paths: [...state.paths, [...currentPath.current]],
      };
      setDraft(draftKey, next);
    }
    currentPath.current = [];
  }, [state, draftKey, setDraft]);

  // ---------- undo ----------
  const undo = useCallback(() => {
    if (state.paths.length === 0) return;
    const next: FreeDraftState = {
      ...state,
      paths: state.paths.slice(0, -1),
    };
    setDraft(draftKey, next);
  }, [state, draftKey, setDraft]);

  // ---------- clear ----------
  const clearDraft = useCallback(() => {
    setDraft(draftKey, createInitialState(state.canvasWidth, state.canvasHeight));
  }, [state.canvasWidth, state.canvasHeight, draftKey, setDraft]);

  return (
    <div style={containerStyle}>
      <canvas
        ref={canvasRef}
        width={state.canvasWidth}
        height={state.canvasHeight}
        style={canvasStyle}
        onPointerDown={handlePointerDown}
        onPointerMove={handlePointerMove}
        onPointerUp={handlePointerUp}
        onPointerCancel={handlePointerUp}
        aria-label="Free drawing area"
      />
      <div style={toolbarStyle}>
        <button type="button" onClick={undo} style={btnStyle}>
          Visszavonás / Rückgängig
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

const canvasStyle: React.CSSProperties = {
  border: '1px solid #ddd',
  borderRadius: '6px',
  background: '#fff',
  touchAction: 'none', // prevent scroll while drawing
  cursor: 'crosshair',
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
