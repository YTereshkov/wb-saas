<?php

namespace App\Http\Requests;

use App\Modules\Analytics\Queries\ProductTableQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'view' => ['required', Rule::in(ProductTableQuery::VIEWS)],
            'filters' => ['required', 'array'],
            'filters.search' => ['nullable', 'string', 'max:160'],
            'filters.category' => ['nullable', 'integer'],
            'filters.status' => ['required', Rule::in(['all', 'active', 'inactive'])],
            'filters.stock' => ['required', Rule::in(['all', 'low', 'out'])],
            'filters.performance' => ['required', Rule::in(['all', 'decline', 'growth'])],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['required', 'distinct', Rule::in(ProductTableQuery::COLUMNS)],
            'sort_column' => ['required', Rule::in(['title', 'revenue', 'sales', 'dynamics', 'buyout', 'returns', 'stock', 'coverage'])],
            'sort_direction' => ['required', Rule::in(['asc', 'desc'])],
            'page_size' => ['required', Rule::in([25, 50, 100])],
            'return_to' => ['required', 'string', 'max:2048', 'regex:/^\/(?!\/)/'],
        ];
    }
}
