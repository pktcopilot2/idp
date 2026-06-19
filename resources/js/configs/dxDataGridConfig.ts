import { exportDataGrid } from 'devextreme/excel_exporter';
import dxDataGrid from 'devextreme/ui/data_grid';
import { Workbook } from 'exceljs';
import { saveAs } from 'file-saver';
import type { DxDataGridTypes } from 'devextreme-vue/data-grid';

export const dxDataGridBaseProps = {
    noDataText: 'No data available',
    columnAutoWidth: true,
    showBorders: true,
    hoverStateEnabled: true,
    rowAlternationEnabled: true,
    remoteOperations: {
        paging: true,
        filtering: true,
        sorting: true,
    },
};

export const dxDataGridFilterRowProps = {
    visible: true,
    applyFilter: 'auto' as const,
};

export const dxDataGridHeaderFilterProps = {
    visible: true,
};

export const dxDataGridPagingProps = {
    pageSize: 10,
};

export const dxDataGridPagerProps = {
    visible: true,
    allowedPageSizes: [10, 20, 50, 'all'],
    showPageSizeSelector: true,
    showNavigationButtons: true,
    showInfo: true,
};

export const dxDataGridColumnChooserProps = {
    enabled: true,
    mode: 'select' as const,
};

export const dxDataGridExportProps = {
    enabled: true,
    allowExportSelectedData: true,
};

export const dxDataGridLoadPanelProps = {
    enabled: true,
    showIndicator: true,
    showPane: true,
    text: 'Memuat data...',
    shading: true,
    shadingColor: 'rgba(9, 14, 28, 0.05)',
};

export const dxDataGridSelectionProps = {
    mode: 'multiple' as const,
    showCheckBoxesMode: 'always' as const,
    selectAllMode: 'page' as const,
};

export type DxExportConfig = {
    worksheetName?: string;
    filePrefix?: string;
};

export const buildDxOnExporting =
    (config: DxExportConfig = {}) =>
    async (e: DxDataGridTypes.ExportingEvent) => {
        const workbook = new Workbook();
        const worksheet = workbook.addWorksheet(config.worksheetName ?? 'Data');

        await exportDataGrid({
            component: e.component,
            worksheet,
            autoFilterEnabled: true,
        });

        const buffer = await workbook.xlsx.writeBuffer();
        const datePart = new Date().toISOString().slice(0, 10);
        const fileName = `${config.filePrefix ?? 'export'}-${datePart}.xlsx`;

        saveAs(new Blob([buffer], { type: 'application/octet-stream' }), fileName);
        e.cancel = true;
    };

export type DxToolbarConfig = {
    onRefresh?: () => void;
    showExportButton?: boolean;
};

export const buildDxToolbarItems = (config: DxToolbarConfig = {}) => {
    const items: Array<Record<string, unknown>> = [];

    if (config.showExportButton !== false) {
        items.push({ name: 'exportButton' });
    }

    if (typeof config.onRefresh === 'function') {
        items.push({
            location: 'after',
            widget: 'dxButton',
            options: {
                icon: 'refresh',
                stylingMode: 'outlined',
                onClick: config.onRefresh,
            },
        });
    }

    return items;
};

let isDxDataGridDefaultsInitialized = false;
const DEFAULT_DX_SWATCH_CLASS = 'dx-swatch-custom-scheme';

export const initializeDxDataGridDefaults = () => {
    if (isDxDataGridDefaultsInitialized) {
        return;
    }

    if (typeof document !== 'undefined') {
        document.body.classList.add(DEFAULT_DX_SWATCH_CLASS);
    }

    dxDataGrid.defaultOptions({
        options: {
            ...dxDataGridBaseProps,
            filterRow: dxDataGridFilterRowProps,
            headerFilter: dxDataGridHeaderFilterProps,
            paging: dxDataGridPagingProps,
            pager: dxDataGridPagerProps,
            export: dxDataGridExportProps,
            loadPanel: dxDataGridLoadPanelProps,
            selection: dxDataGridSelectionProps,
            onExporting: buildDxOnExporting(),
            onToolbarPreparing: (e: DxDataGridTypes.ToolbarPreparingEvent) => {
                const items = e.toolbarOptions?.items ?? [];

                const hasRefreshButton = items.some(
                    (item) => item && typeof item === 'object' && 'name' in item && item.name === 'refreshButton',
                );

                if (!hasRefreshButton) {
                    items.push({
                        name: 'refreshButton',
                        location: 'after',
                        widget: 'dxButton',
                        options: {
                            icon: 'refresh',
                            stylingMode: 'outlined',
                            onClick: () => e.component.refresh(),
                        },
                    });
                }

                e.toolbarOptions = {
                    ...(e.toolbarOptions ?? {}),
                    items,
                };
            },
        },
    });

    isDxDataGridDefaultsInitialized = true;
};
