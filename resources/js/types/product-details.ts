import type { KpiItem, ProductSignal } from './analytics';

export type ChartPoint = {
    label: string;
    [key: string]: string | number | null;
};

export type ProductDetailsData = {
    product: {
        id: number;
        title: string;
        vendorCode: string;
        nmId: string;
        imageUrl: string | null;
        category: string | null;
    };
    signal: ProductSignal | null;
    insights: {
        overview: string;
        sales: string;
        stocks: string;
    };
    kpis: KpiItem[];
    salesKpis: KpiItem[];
    series: {
        granularity: 'day' | 'week' | 'month';
        revenue: ChartPoint[];
        sales: ChartPoint[];
    };
    weekly: Array<{
        label: string;
        orders: number;
        sales: number;
        buyout: number | null;
        returnsRate: number | null;
    }>;
    funnel: {
        orders: number;
        sales: number;
        notBought: number;
        buyout: number | null;
        returnsRate: number | null;
    };
    stock: {
        total: number;
        averageDailySales: number;
        coverage: number | null;
        outDate: string | null;
        recommendedSupply: number;
        series: ChartPoint[];
        warehouses: Array<{
            name: string;
            quantity: number;
            share: number;
            coverage: number | null;
            urgency: string;
        }>;
    };
};
