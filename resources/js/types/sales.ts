import type { KpiItem, ProductAnalyticsRow } from './analytics';
import type { ChartPoint } from './product-details';

export type SalesAnalyticsData = {
    state: 'empty' | 'ready';
    insights?: {
        dynamics: string;
        ordersSales: string;
        buyoutReturns: string;
        products: string;
    };
    kpis?: KpiItem[];
    series?: {
        granularity: 'day' | 'week' | 'month';
        revenuePoints: ChartPoint[];
        ordersSalesPoints: ChartPoint[];
        qualityPoints: ChartPoint[];
        revenueDay: ChartPoint[];
        revenueWeek: ChartPoint[];
        ordersSalesDay: ChartPoint[];
        ordersSalesWeek: ChartPoint[];
        qualityDay: ChartPoint[];
        qualityWeek: ChartPoint[];
    };
    products?: {
        contribution: Array<
            ProductAnalyticsRow & {
                comparisonRevenueKopecks: number;
                deltaKopecks: number;
            }
        >;
        gap: Array<ProductAnalyticsRow & { gap: number }>;
        quality: SalesQualityRow[];
    };
};

export type SalesQualityRow = ProductAnalyticsRow & {
    comparisonRevenueKopecks: number;
    deltaKopecks: number;
    buyoutChange: number | null;
    returnsChange: number | null;
};

export type SalesProductTableData = {
    state: 'empty' | 'ready';
    rows?: Array<
        ProductAnalyticsRow & {
            comparisonRevenueKopecks: number;
            deltaKopecks: number;
        }
    >;
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
        performance: string;
        sort: string;
        direction: string;
    };
    categories?: Array<{ id: number; name: string }>;
};
