type RawState = Record<string, unknown>;
type RawColumn = Record<string, unknown>;

// Top-level state keys to always drop
const STATE_DROP = new Set(['selectedRowKeys', 'focusedRowKey', 'allowedPageSizes', 'filterPanel']);

// Default values — skip when equal to default
const DEFAULTS: Record<string, unknown> = { searchText: '', pageIndex: 0, pageSize: 10, filterValue: null };

// Per-column props that carry actual user state
const COLUMN_STATE_PROPS = new Set([
    'sortIndex', 'sortOrder', 'filterValue', 'filterValues', 'selectedFilterOperation',
]);

function compactState(state: RawState): RawState {
    const result: RawState = {};

    for (const [key, value] of Object.entries(state)) {
        if (STATE_DROP.has(key)) continue;

        // Skip values that equal their default
        if (key in DEFAULTS) {
            const def = DEFAULTS[key];
            if (value === def || (value == null && def == null)) continue;
        }

        if (key === 'columns' && Array.isArray(value)) {
            const cols = (value as RawColumn[])
                .map((col) => {
                    const slim: RawColumn = {};
                    if (col.dataField != null) slim.dataField = col.dataField;
                    for (const p of COLUMN_STATE_PROPS) {
                        if (col[p] !== undefined) slim[p] = col[p];
                    }
                    return slim;
                })
                // Only include columns that have actual state (beyond just the identifier)
                .filter((col) => Object.keys(col).some((k) => COLUMN_STATE_PROPS.has(k)));

            if (cols.length > 0) result.columns = cols;
            continue;
        }

        result[key] = value;
    }

    return result;
}

export function useDxGridUrlState(paramKey: string) {
    const customSave = (state: RawState) => {
        const compact = compactState(state);
        const url = new URL(window.location.href);

        if (Object.keys(compact).length === 0) {
            url.searchParams.delete(paramKey);
        } else {
            url.searchParams.set(paramKey, JSON.stringify(compact));
        }

        window.history.replaceState(null, '', url.toString());
    };

    const customLoad = (): RawState | null => {
        const raw = new URLSearchParams(window.location.search).get(paramKey);
        if (!raw) return null;
        try {
            return JSON.parse(raw) as RawState;
        } catch {
            return null;
        }
    };

    return {
        stateStoringProps: {
            enabled: true,
            type: 'custom' as const,
            savingTimeout: 400,
            customSave,
            customLoad,
        },
    };
}
