import axios from 'axios';
import CustomStore from 'devextreme/data/custom_store';

export type DxRemoteLoadResponse<TRow> = {
    data: TRow[];
    totalCount?: number;
};

export type CreateDxRemoteStoreOptions = {
    url: string | (() => string);
    key?: string;
};

const DX_LOAD_OPTION_NAMES = [
    'skip',
    'take',
    'sort',
    'group',
    'requireGroupCount',
    'filter',
    'requireTotalCount',
    'searchExpr',
    'searchOperation',
    'searchValue',
] as const;

export const serializeDxLoadOptions = (loadOptions: Record<string, unknown>) => {
    const options: Record<string, unknown> = {};

    for (const optionName of DX_LOAD_OPTION_NAMES) {
        const value = loadOptions[optionName];
        if (value === undefined || value === null || value === '') {
            continue;
        }

        options[optionName] =
            typeof value === 'object' ? JSON.stringify(value) : value;
    }

    return options;
};

export const createDxRemoteStore = <TRow, TKey extends number | string = number>(
    options: CreateDxRemoteStoreOptions,
) => {
    const key = options.key ?? 'id';

    return new CustomStore<TRow, TKey>({
        key,
        load: async (loadOptions) => {
            const resolvedUrl = typeof options.url === 'function'
                ? options.url()
                : options.url;

            const { data } = await axios.get<DxRemoteLoadResponse<TRow>>(resolvedUrl, {
                params: serializeDxLoadOptions(loadOptions as Record<string, unknown>),
            });

            return {
                data: data.data,
                totalCount: data.totalCount,
            };
        },
    });
};
