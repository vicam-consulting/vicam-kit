import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';
import type { UserResponse } from './generated';

export interface Auth {
    user: UserResponse;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    badge?: number;
}

export type NavItemGroup = {
    title: string;
    items: NavItem[];
};

export interface FlashMessages {
    success?: string | string[];
    error?: string | string[];
    info?: string | string[];
    warning?: string | string[];
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    flash?: FlashMessages;
};

export type BreadcrumbItemType = BreadcrumbItem;

// spatie/laravel-data's paginated response type
export type PaginatedDataCollection<T> = {
    data: T[];
    links: {
        url: string | null;
        label: string;
        page: number | null;
        active: boolean;
    }[];
    meta: {
        current_page: number;
        first_page_url: string;
        from: number;
        last_page: number;
        last_page_url: string;
        next_page_url: string | null;
        path: string;
        per_page: number;
        prev_page_url: string | null;
        to: number;
        total: number;
    };
};
