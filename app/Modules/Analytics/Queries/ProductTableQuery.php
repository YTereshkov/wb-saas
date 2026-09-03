<?php

namespace App\Modules\Analytics\Queries;

use App\Models\User;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\SellerAccounts\Models\SavedView;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

final readonly class ProductTableQuery
{
    public const VIEWS = ['all', 'attention', 'decline', 'low_stock'];

    public const COLUMNS = ['revenue', 'sales', 'dynamics', 'buyout', 'returns', 'stock', 'coverage'];

    private const SORTS = ['title', 'revenue', 'sales', 'dynamics', 'buyout', 'returns', 'stock', 'coverage'];

    public function __construct(private ProductAnalyticsQuery $analytics) {}

    /** @return array<string, mixed> */
    public function get(User $user, SellerAccount $account, AnalyticsPeriod $period, Request $request, string $view): array
    {
        abort_unless(in_array($view, self::VIEWS, true), 404);

        /** @var SavedView|null $saved */
        $saved = SavedView::query()
            ->where('user_id', $user->id)
            ->where('seller_account_id', $account->id)
            ->where('section', 'products')
            ->where('view_key', $view)
            ->first();
        $params = $this->params($request, $saved);
        $base = $this->analytics->base($account, $period);
        $counts = $this->counts($base, $period);
        $query = $this->applyView(clone $base, $view, $period);
        $this->applyFilters($query, $params, $period);
        $this->applySort($query, $params['sort'], $params['direction']);

        $paginator = $query->paginate(
            perPage: $params['perPage'],
            page: max(1, (int) $request->integer('page', 1)),
        )->withQueryString();
        $rows = collect($paginator->items())
            ->map(fn (object $row): array => $this->analytics->toRow($row))
            ->all();

        return [
            'view' => $view,
            'counts' => $counts,
            'rows' => $rows,
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => [
                'search' => $params['search'],
                'category' => $params['category'],
                'status' => $params['status'],
                'stock' => $params['stock'],
                'performance' => $params['performance'],
                'sort' => $params['sort'],
                'direction' => $params['direction'],
            ],
            'columns' => $params['columns'],
            'isSaved' => $saved !== null,
            'categories' => $account->categories()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($category): array => ['id' => $category->id, 'name' => $category->name])
                ->all(),
        ];
    }

    /** @return array{search: string, category: int|null, status: string, stock: string, performance: string, sort: string, direction: 'asc'|'desc', perPage: int, columns: list<string>} */
    private function params(Request $request, ?SavedView $saved): array
    {
        $savedFilters = is_array($saved?->filters) ? $saved->filters : [];
        $value = static function (string $key, int|string|null $default) use ($request, $savedFilters): int|string|null {
            $candidate = $request->query->has($key)
                ? $request->query($key)
                : ($savedFilters[$key] ?? $default);

            return is_int($candidate) || is_string($candidate) ? $candidate : $default;
        };
        $sort = (string) $value('sort', $saved === null ? 'revenue' : $saved->sort_column);
        $direction = (string) $value('direction', $saved === null ? 'desc' : $saved->sort_direction);
        $perPage = (int) $value('per_page', $saved === null ? 25 : $saved->page_size);
        $savedColumns = is_array($saved?->columns)
            ? array_values(array_filter($saved->columns, is_string(...)))
            : [];
        $columns = $savedColumns !== []
            ? array_values(array_intersect(self::COLUMNS, $savedColumns))
            : self::COLUMNS;
        $categoryValue = $value('category', null);
        $category = (is_int($categoryValue) || (is_string($categoryValue) && ctype_digit($categoryValue)))
            && (int) $categoryValue > 0 ? (int) $categoryValue : null;

        return [
            'search' => trim((string) $value('search', '')),
            'category' => $category,
            'status' => in_array($status = (string) $value('status', 'all'), ['all', 'active', 'inactive'], true) ? $status : 'all',
            'stock' => in_array($stock = (string) $value('stock', 'all'), ['all', 'low', 'out'], true) ? $stock : 'all',
            'performance' => in_array($performance = (string) $value('performance', 'all'), ['all', 'decline', 'growth'], true) ? $performance : 'all',
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'revenue',
            'direction' => in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc',
            'perPage' => in_array($perPage, [25, 50, 100], true) ? $perPage : 25,
            'columns' => $columns !== [] ? $columns : ['revenue', 'sales', 'dynamics', 'stock'],
        ];
    }

    /** @return array{all: int, attention: int, decline: int, lowStock: int} */
    private function counts(Builder $base, AnalyticsPeriod $period): array
    {
        return [
            'all' => (clone $base)->count('products.id'),
            'attention' => (clone $base)->whereNotNull('product_signals.id')->count('products.id'),
            'decline' => (clone $base)->whereRaw(ProductAnalyticsQuery::DYNAMICS_EXPRESSION.' < 0')->count('products.id'),
            'lowStock' => (clone $base)
                ->where('current_metrics.sales', '>', 0)
                ->whereRaw(ProductAnalyticsQuery::COVERAGE_EXPRESSION.' <= 7', [$period->days()])
                ->count('products.id'),
        ];
    }

    private function applyView(Builder $query, string $view, AnalyticsPeriod $period): Builder
    {
        return match ($view) {
            'attention' => $query->whereNotNull('product_signals.id'),
            'decline' => $query->whereRaw(ProductAnalyticsQuery::DYNAMICS_EXPRESSION.' < 0'),
            'low_stock' => $query
                ->where('current_metrics.sales', '>', 0)
                ->whereRaw(ProductAnalyticsQuery::COVERAGE_EXPRESSION.' <= 7', [$period->days()]),
            default => $query,
        };
    }

    /** @param array<string, mixed> $params */
    private function applyFilters(Builder $query, array $params, AnalyticsPeriod $period): void
    {
        if ($params['search'] !== '') {
            $search = '%'.$params['search'].'%';
            $query->where(function (Builder $nested) use ($search): void {
                $nested->whereLike('products.title', $search, caseSensitive: false)
                    ->orWhereLike('products.vendor_code', $search, caseSensitive: false)
                    ->orWhereLike('products.nm_id', $search, caseSensitive: false);
            });
        }

        if ($params['category'] !== null) {
            $query->where('products.category_id', $params['category']);
        }

        if ($params['status'] !== 'all') {
            $query->where('products.is_active', $params['status'] === 'active');
        }

        if ($params['stock'] === 'out') {
            $query->whereRaw('COALESCE(latest_stock.quantity, 0) = 0');
        } elseif ($params['stock'] === 'low') {
            $query->where('current_metrics.sales', '>', 0)
                ->whereRaw(ProductAnalyticsQuery::COVERAGE_EXPRESSION.' <= 7', [$period->days()]);
        }

        if ($params['performance'] === 'decline') {
            $query->whereRaw(ProductAnalyticsQuery::DYNAMICS_EXPRESSION.' < 0');
        } elseif ($params['performance'] === 'growth') {
            $query->whereRaw(ProductAnalyticsQuery::DYNAMICS_EXPRESSION.' > 0');
        }
    }

    /** @param 'asc'|'desc' $direction */
    private function applySort(Builder $query, string $sort, string $direction): void
    {
        $column = match ($sort) {
            'title' => 'products.title',
            'sales' => 'sales',
            'dynamics' => 'dynamics',
            'buyout' => 'buyout',
            'returns' => 'returns_rate',
            'stock' => 'stock',
            'coverage' => 'coverage',
            default => 'revenue_kopecks',
        };

        $query->orderBy($column, $direction)->orderBy('products.id');
    }
}
