import type { ProductAnalyticsRow } from './analytics';

export type StockView = 'all' | 'supply' | 'out_of_stock' | 'no_movement';

export type StockRow = ProductAnalyticsRow & {
    averageDailySales: number;
    outDate: string | null;
    recommendedSupply: number;
    outOfStockDays: number;
    estimatedMissedSales: number;
    daysWithoutSales: number;
    recentSales: number;
    noMovementReason: string;
    action: string;
    signalTone: 'danger' | 'warning' | 'primary';
};

export type StockAnalyticsData = {
    state: 'empty' | 'unavailable' | 'ready';
    view: StockView;
    coverage?: {
        start: string | null;
        end: string | null;
    };
    counts?: {
        all: number;
        supply: number;
        outOfStock: number;
        noMovement: number;
    };
    insight?: {
        tone: 'warning' | 'danger' | 'info';
        title: string;
        detail: string;
    };
    notice?: string;
    rows?: StockRow[];
    pagination?: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters?: {
        search: string;
        category: number | null;
        warehouse: number | null;
        sort: string;
        direction: string;
    };
    categories?: Array<{ id: number; name: string }>;
    warehouses?: Array<{ id: number; name: string }>;
};
