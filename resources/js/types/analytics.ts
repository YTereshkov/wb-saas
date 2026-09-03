export type ProductSignal = {
    type: string;
    priority: number;
    severity: 'danger' | 'warning' | 'info' | 'success';
    fact: string;
    risk: string;
    recommendation: string;
};

export type KpiItem = {
    key: string;
    label: string;
    value: number | null;
    change: number | null;
    format: 'money' | 'number' | 'percent';
    changeFormat: 'percent' | 'pp';
    inverse?: boolean;
};

export type ProductAnalyticsRow = {
    id: number;
    title: string;
    vendorCode: string;
    nmId: string;
    imageUrl: string | null;
    category: string | null;
    active: boolean;
    revenueKopecks: number;
    orders: number;
    sales: number;
    dynamics: number | null;
    buyout: number | null;
    returnsRate: number | null;
    stock: number;
    coverage: number | null;
    signal: ProductSignal | null;
};

export type OverviewData = {
    state: 'empty' | 'ready';
    insight?: string;
    kpis?: KpiItem[];
    series?: {
        granularity: 'day' | 'week' | 'month';
        points: Array<{
            label: string;
            current: number;
            comparison: number | null;
        }>;
        day: Array<{
            label: string;
            current: number;
            comparison: number | null;
        }>;
        week: Array<{ label: string; current: number; comparison: number }>;
    };
    attention?: {
        count: number;
        signals: Array<{
            productId: number;
            productTitle: string;
            imageUrl: string | null;
            type: string;
            severity: string;
            fact: string;
            risk: string;
            recommendation: string;
            href: string;
        }>;
    };
    products?: {
        leaders: ProductAnalyticsRow[];
        decline: ProductAnalyticsRow[];
        risk: ProductAnalyticsRow[];
    };
};

export type ProductView = 'all' | 'attention' | 'decline' | 'low_stock';

export type ProductColumn =
    | 'revenue'
    | 'sales'
    | 'dynamics'
    | 'buyout'
    | 'returns'
    | 'stock'
    | 'coverage';

export type ProductTableData = {
    state?: 'empty';
    view: ProductView;
    counts?: {
        all: number;
        attention: number;
        decline: number;
        lowStock: number;
    };
    rows?: ProductAnalyticsRow[];
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
        status: string;
        stock: string;
        performance: string;
        sort: string;
        direction: string;
    };
    columns?: ProductColumn[];
    isSaved?: boolean;
    categories?: Array<{ id: number; name: string }>;
};
