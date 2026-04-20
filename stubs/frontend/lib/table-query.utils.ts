import type { SortingState } from '@tanstack/vue-table';
import { query } from '@vortechron/query-builder-ts';

export type TableFilters = Record<
    string,
    string | number | boolean | null | undefined
>;

export type TableQueryOptions = {
    baseUrl: string;
    search?: string;
    sorting?: SortingState;
    perPage?: number;
    filters?: TableFilters;
};

const buildSortParams = (sorting: SortingState = []): string[] =>
    sorting.map((sort) => `${sort.desc ? '-' : ''}${sort.id}`);

export const parseSortParams = (
    sortValues: string | string[] = [],
): SortingState =>
    (Array.isArray(sortValues) ? sortValues : [sortValues])
        .flatMap((value) => value.split(','))
        .filter((param) => param.length > 0)
        .map((param) => ({
            id: param.startsWith('-') ? param.slice(1) : param,
            desc: param.startsWith('-'),
        }));

export const buildTableQuery = ({
    baseUrl,
    search,
    sorting = [],
    perPage,
    filters = {},
}: TableQueryOptions) => {
    const sortParams = buildSortParams(sorting);

    return query(baseUrl)
        .tap((builder) => {
            if (sortParams.length) {
                builder.sort(...sortParams);
            }
        })
        .when(Boolean(search), (builder) =>
            builder.filter('search', String(search)),
        )
        .when(typeof perPage === 'number', (builder) =>
            builder.param('perPage', String(perPage)),
        )
        .tap((builder) => {
            // Add custom filters
            Object.entries(filters).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') {
                    builder.filter(key, String(value));
                }
            });
        })
        .build();
};
