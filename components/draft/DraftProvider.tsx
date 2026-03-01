/**
 * Draft Engine v2 – DraftProvider
 *
 * Central state store for all draft instances.
 * State is keyed by testId + questionId so accordion collapse / re-render
 * never destroys user work.
 */
import React, {
  createContext,
  useCallback,
  useContext,
  useRef,
  useState,
} from 'react';
import type { DraftKey, DraftState } from './types';

/* ------------------------------------------------------------------ */
/*  Context shape                                                      */
/* ------------------------------------------------------------------ */

interface DraftContextValue {
  /** Get current state for a draft key (returns undefined if none) */
  getDraft: (key: DraftKey) => DraftState | undefined;
  /** Set / replace state for a draft key */
  setDraft: (key: DraftKey, state: DraftState) => void;
  /** Remove a draft entirely */
  removeDraft: (key: DraftKey) => void;
  /** Clear every stored draft */
  clearAll: () => void;
}

const DraftContext = createContext<DraftContextValue | null>(null);

/* ------------------------------------------------------------------ */
/*  Provider                                                           */
/* ------------------------------------------------------------------ */

interface DraftProviderProps {
  children: React.ReactNode;
}

export function DraftProvider({ children }: DraftProviderProps) {
  // Use a plain object ref as the source of truth so that individual cell
  // edits never cause a full tree re-render.  A version counter forces
  // consumers to re-read when they call getDraft after setDraft.
  const store = useRef<Record<DraftKey, DraftState>>({});
  const [, setVersion] = useState(0);
  const bump = useCallback(() => setVersion((v) => v + 1), []);

  const getDraft = useCallback(
    (key: DraftKey): DraftState | undefined => store.current[key],
    [],
  );

  const setDraft = useCallback(
    (key: DraftKey, state: DraftState) => {
      store.current[key] = state;
      bump();
    },
    [bump],
  );

  const removeDraft = useCallback(
    (key: DraftKey) => {
      delete store.current[key];
      bump();
    },
    [bump],
  );

  const clearAll = useCallback(() => {
    store.current = {};
    bump();
  }, [bump]);

  const value: DraftContextValue = {
    getDraft,
    setDraft,
    removeDraft,
    clearAll,
  };

  return (
    <DraftContext.Provider value={value}>{children}</DraftContext.Provider>
  );
}

/* ------------------------------------------------------------------ */
/*  Hook                                                               */
/* ------------------------------------------------------------------ */

export function useDraft() {
  const ctx = useContext(DraftContext);
  if (!ctx) {
    throw new Error('useDraft must be used inside <DraftProvider>');
  }
  return ctx;
}

export { DraftContext };
