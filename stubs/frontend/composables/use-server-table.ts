import { router } from '@inertiajs/vue3';
import type { SortingState } from '@tanstack/vue-table';
import { computed, ref, watch } from 'vue';
import type { WatchSource } from 'vue';

import { buildTableQuery, parseSortParams } from '@/lib/table-query.utils';

export type TableFilters = Record<
    string,
    string | number | boolean | null | undefined
>;

export type UseServerTableOptions = {
    /**
     * The base URL for the table index route
     */
    baseUrl: string;

    /**
     * Initial filter values from the server
     */
    initialFilters: () => {
        search?: string;
        sort?: string;
        perPage?: number;
        [key: string]: any;
    };

    /**
     * Default items per page
     */
    defaultPerPage?: number;

    /**
     * The Inertia prop key to reload (e.g., 'cases', 'users', 'products')
     * If not provided, the entire page will reload
     */
    inertiaKey?: string;

    /**
     * Custom filters to include in the query
     */
    customFilters?: TableFilters;

    /**
     * Enable or disable specific features
     */
    features?: {
        search?: boolean;
        pagination?: boolean;
    };
};

export function useServerTable({
    baseUrl,
    initialFilters,
    defaultPerPage = 20,
    inertiaKey,
    customFilters = {},
    features = { search: true, pagination: true },
}: UseServerTableOptions) {
    const filters = initialFilters();
    const sorting = ref<SortingState>(parseSortParams(filters.sort));
    const globalFilter = ref<string>(filters.search ?? '');
    const perPage = ref<number>(filters.perPage ?? defaultPerPage);

    let isSyncing = false;

    /**
     * Sync state when filters change (e.g., browser back/forward navigation)
     */
    watch(initialFilters, (newFilters) => {
        isSyncing = true;
        sorting.value = parseSortParams(newFilters.sort);
        globalFilter.value = newFilters.search ?? '';
        perPage.value = newFilters.perPage ?? defaultPerPage;
        isSyncing = false;
    });

    /**
     * Navigate to the table with current state
     */
    const navigateToTable = (additionalParams?: Record<string, any>) => {
        const url = buildTableQuery({
            baseUrl,
            search: features.search ? globalFilter.value : undefined,
            sorting: sorting.value,
            perPage:
                features.pagination && perPage.value !== defaultPerPage
                    ? perPage.value
                    : undefined,
            filters: {
                ...customFilters,
                ...additionalParams,
            },
        });

        router.visit(url, {
            ...(inertiaKey && { only: [inertiaKey] }),
            preserveState: true,
            preserveScroll: true,
            preserveErrors: true,
        });
    };

    /**
     * Reset all filters and navigate to base URL
     */
    const resetFilters = () => {
        sorting.value = [];
        globalFilter.value = '';
        perPage.value = defaultPerPage;

        // Navigate to clean base URL
        router.visit(baseUrl, {
            ...(inertiaKey && { only: [inertiaKey] }),
            preserveState: true,
            preserveScroll: true,
            preserveErrors: true,
        });
    };

    /**
     * Whether any filters are currently active
     */
    const hasActiveFilters = computed(() => {
        return (
            sorting.value.length > 0 ||
            (features.search && globalFilter.value !== '') ||
            (features.pagination && perPage.value !== defaultPerPage)
        );
    });

    /**
     * Watch for changes and automatically navigate
     */
    const watchTargets: WatchSource[] = [sorting];

    if (features.search) {
        watchTargets.push(globalFilter);
    }

    if (features.pagination) {
        watchTargets.push(perPage);
    }

    watch(
        watchTargets,
        () => {
            if (isSyncing) {
                return;
            }

            navigateToTable();
        },
        { deep: true },
    );

    return {
        sorting,
        globalFilter,
        perPage,
        navigateToTable,
        resetFilters,
        hasActiveFilters,
    };
}
