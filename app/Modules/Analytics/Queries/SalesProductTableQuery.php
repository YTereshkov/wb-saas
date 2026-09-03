<?php

namespace App\Modules\Analytics\Queries;

use App\Models\User;
use App\Modules\SellerAccounts\Models\SellerAccount;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

final readonly class SalesProductTableQuery
{
    private const SORTS = ['title', 'revenue', 'comparison', 'dynamics', 'sales', 'buyout', 'returns'];

    public function __construct(
        private AnalyticsContextQuery $context,
        private ProductAnalyticsQuery $products,
    ) {}

    /** @return array<string, mixed> */
    public function forUser(User $user, Request $request): array
    {
        ['account' => $account, 'period' => $period] = $this->context->resolveForUser($user);

        if (! $account instanceof SellerAccount) {
            return ['state' => 'empty'];
        }

        $search = trim((string) $this->scalarQuery($request, 'search', ''));
        $categoryValue = $this->scalarQuery($request, 'category');
        $category = (is_int($categoryValue) || (is_string($categoryValue) && ctype_digit($categoryValue)))
            && (int) $categoryValue > 0 ? (int) $categoryValue : null;
        $performanceValue = (string) $this->scalarQuery($request, 'performance', 'all');
        $performance = in_array($performanceValue, ['all', 'growth', 'decline'], true) ? $performanceValue : 'all';
        $sortValue = (string) $this->scalarQuery($request, 'sort', 'revenue');
        $sort = in_array($sortValue, self::SORTS, true) ? $sortValue : 'revenue';
        $direction = $this->scalarQuery($request, 'direction') === 'asc' ? 'asc' : 'desc';
        $perPageValue = (int) $this->scalarQuery($request, 'per_page', 25);
        $perPage = in_array($perPageValue, [25, 50, 100], true) ? $perPageValue : 25;
        $query = $this->products->base($account, $period);

        $this->applyFilters($query, $search, $category, $performance);
        $this->applySort($query, $sort, $direction);

        $paginator = $query->paginate($perPage, page: max(1, $request->integer('page', 1)))->withQueryString();

        return [
            'state' => 'ready',
            'rows' => collect($paginator->items())->map(function (object $row): array {
                $data = get_object_vars($row);

                return [
                    ...$this->products->toRow($row),
                    'comparisonRevenueKopecks' => (int) $data['comparison_revenue_kopecks'],
                    'deltaKopecks' => (int) $data['revenue_kopecks'] - (int) $data['comparison_revenue_kopecks'],
                ];
            })->all(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => compact('search', 'category', 'performance', 'sort', 'direction'),
            'categories' => $account->categories()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($item): array => ['id' => $item->id, 'name' => $item->name])
                ->all(),
        ];
    }

    private function scalarQuery(Request $request, string $key, int|string|null $default = null): int|string|null
    {
        $value = $request->query($key, $default);

        return is_int($value) || is_string($value) ? $value : $default;
    }

    private function applyFilters(Builder $query, string $search, ?int $category, string $performance): void
    {
        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(function (Builder $nested) use ($needle): void {
                $nested->whereLike('products.title', $needle, caseSensitive: false)
                    ->orWhereLike('products.vendor_code', $needle, caseSensitive: false)
                    ->orWhereLike('products.nm_id', $needle, caseSensitive: false);
            });
        }

        if ($category !== null) {
            $query->where('products.category_id', $category);
        }

        if ($performance === 'growth') {
            $query->whereRaw(ProductAnalyticsQuery::DYNAMICS_EXPRESSION.' > 0');
        } elseif ($performance === 'decline') {
            $query->whereRaw(ProductAnalyticsQuery::DYNAMICS_EXPRESSION.' < 0');
        }
    }

    /** @param 'asc'|'desc' $direction */
    private function applySort(Builder $query, string $sort, string $direction): void
    {
        $column = match ($sort) {
            'title' => 'products.title',
            'comparison' => 'comparison_revenue_kopecks',
            'dynamics' => 'dynamics',
            'sales' => 'sales',
            'buyout' => 'buyout',
            'returns' => 'returns_rate',
            default => 'revenue_kopecks',
        };

        $query->orderBy($column, $direction)->orderBy('products.id');
    }
}
