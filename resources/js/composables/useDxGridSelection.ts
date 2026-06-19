import { computed, ref } from 'vue';
import type { DxDataGrid } from 'devextreme-vue/data-grid';
import type { DxDataGridTypes } from 'devextreme-vue/data-grid';

export function useDxGridSelection<TRow = Record<string, unknown>, TKey = unknown>() {
    const gridRef = ref<InstanceType<typeof DxDataGrid> | null>(null);
    const selectedRows = ref<TRow[]>([]);
    const selectedKeys = ref<TKey[]>([]);

    const hasSelection = computed(() => selectedRows.value.length > 0);
    const selectionCount = computed(() => selectedRows.value.length);

    const onSelectionChanged = (e: DxDataGridTypes.SelectionChangedEvent) => {
        selectedRows.value = e.selectedRowsData as TRow[];
        selectedKeys.value = e.selectedRowKeys as TKey[];
    };

    const onRowClick = (e: DxDataGridTypes.RowClickEvent) => {
        if (e.rowType !== 'data') return;
        const isSelected = (selectedKeys.value as unknown[]).includes(e.key);
        if (isSelected) {
            e.component.deselectRows([e.key]);
        } else {
            e.component.selectRows([e.key], true);
        }
    };

    const clearSelection = () => {
        gridRef.value?.instance?.clearSelection();
    };

    return {
        gridRef,
        selectedRows,
        selectedKeys,
        hasSelection,
        selectionCount,
        onSelectionChanged,
        onRowClick,
        clearSelection,
    };
}
