<?php

namespace Tests\Feature\TenantIsolation;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\SellerAccounts\Models\SellerAccount;
use App\Modules\SellerAccounts\Models\UserPreference;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'tenant:product'])
            ->get('/_test/tenant/products/{product}', fn (Product $product): array => [
                'id' => $product->id,
            ]);
    }

    public function test_policy_and_middleware_allow_only_the_account_owner(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $sellerAccount = SellerAccount::factory()->for($owner)->create();
        $product = $this->createProduct($sellerAccount);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $sellerAccount));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $product));
        $this->assertFalse(Gate::forUser($stranger)->allows('view', $sellerAccount));
        $this->assertFalse(Gate::forUser($stranger)->allows('view', $product));

        $this->actingAs($owner)
            ->get("/_test/tenant/products/{$product->id}")
            ->assertOk()
            ->assertJson(['id' => $product->id]);

        $this->actingAs($stranger)
            ->get("/_test/tenant/products/{$product->id}")
            ->assertForbidden();
    }

    public function test_preference_cannot_select_another_users_account(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $foreignAccount = SellerAccount::factory()->for($stranger)->create();

        $this->expectException(QueryException::class);

        UserPreference::query()->create([
            'user_id' => $owner->id,
            'active_seller_account_id' => $foreignAccount->id,
        ]);
    }

    public function test_product_cannot_reference_a_category_from_another_account(): void
    {
        $firstAccount = SellerAccount::factory()->create();
        $secondAccount = SellerAccount::factory()->create();
        $foreignCategory = Category::query()->create([
            'seller_account_id' => $secondAccount->id,
            'external_id' => 'foreign-category',
            'name' => 'Чужая категория',
        ]);

        $this->expectException(QueryException::class);

        Product::query()->create([
            'seller_account_id' => $firstAccount->id,
            'category_id' => $foreignCategory->id,
            'nm_id' => '10001',
            'vendor_code' => 'FOREIGN-CATEGORY',
            'title' => 'Некорректный товар',
        ]);
    }

    private function createProduct(SellerAccount $sellerAccount): Product
    {
        return Product::query()->create([
            'seller_account_id' => $sellerAccount->id,
            'nm_id' => '01234567890123456789',
            'vendor_code' => 'TEST-001',
            'title' => 'Тестовый товар',
        ]);
    }
}
