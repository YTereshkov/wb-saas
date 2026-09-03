export type SellerAccountSummary = {
    id: number;
    name: string;
    status: string;
    statusLabel?: string;
};

export type AnalyticsContext = {
    accounts: SellerAccountSummary[];
    activeAccount: SellerAccountSummary | null;
    period: {
        preset: string;
        start: string;
        end: string;
        label: string;
        granularity: 'day' | 'week' | 'month';
    };
    comparison: {
        start: string;
        end: string;
        label: string;
        isComplete: boolean;
    };
    coverage: {
        overall: DateCoverage;
        resources: {
            orders: DateCoverage;
            sales: DateCoverage;
            returns: DateCoverage;
            stocks: DateCoverage;
        };
    };
    sync: {
        status: string;
        lastCompletedAt: string | null;
        label: string;
        isStale: boolean;
        missingPermissions: string[];
        unavailableResources: string[];
    } | null;
    periodOptions: Array<{ value: string; label: string }>;
};

export type DateCoverage = {
    start: string | null;
    end: string | null;
};
