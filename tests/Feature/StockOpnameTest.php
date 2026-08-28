<?php

namespace Tests\Feature;

use App\Helpers\JwtClaims;
use App\Models\Company;
use App\Models\Feature;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\StockOpname;
use App\Models\Store;
use App\Models\User;
use App\Models\WifiCredential;
use App\Repository\ProductRepository;
use App\Repository\TransactionRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Store $store;
    private ProductCategory $category;
    private Product $product;
    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Oren POS',
            'slug' => 'oren-pos',
            'plan' => 'pro',
        ]);
        $this->store = Store::create([
            'name' => 'Toko Utama',
            'company_id' => $this->company->id,
        ]);
        $this->category = ProductCategory::create([
            'name' => 'Bahan',
            'slug' => 'bahan-'.$this->company->id,
            'company_id' => $this->company->id,
        ]);
        $this->product = Product::create([
            'name' => 'Cabai',
            'code' => 'CABAI-'.$this->company->id,
            'store_id' => $this->store->id,
            'product_category_id' => $this->category->id,
            'selling_type' => 'Purchase',
            'price' => 10000,
            'status' => true,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'company_id' => $this->company->id,
        ]);
        $this->staff = User::factory()->create([
            'role_id' => $staffRole->id,
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);

        $feature = Feature::create([
            'slug' => 'stock-opname',
            'name' => 'Stock Opname',
            'route' => '/stock-opname',
            'sort_order' => 13,
        ]);
        PlanFeature::create([
            'plan' => 'pro',
            'feature_id' => $feature->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_complete_the_stock_opname_lifecycle(): void
    {
        $created = $this->actingAsJwt($this->admin)->postJson('/api/v1/stock-opnames', [
            'opname_date' => '2026-08-27',
            'store_id' => $this->store->id,
            'product_category_id' => $this->category->id,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'SO-OREN-POS-20260827-001')
            ->assertJsonCount(1, 'data.items');

        $opnameId = $created->json('data.id');
        $itemId = $created->json('data.items.0.id');

        $this->actingAsJwt($this->admin)
            ->getJson('/api/v1/stock-opnames')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $opnameId);

        $this->actingAsJwt($this->admin)
            ->getJson('/api/v1/stock-opnames/'.$opnameId)
            ->assertOk()
            ->assertJsonPath('data.items.0.product_id', $this->product->id);

        $this->actingAsJwt($this->admin)
            ->putJson('/api/v1/stock-opnames/'.$opnameId.'/items/'.$itemId, [
                'counted_stock' => 12,
            ])
            ->assertOk()
            ->assertJsonPath('data.variance', 12);

        $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/stock-opnames/'.$opnameId.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $next = $this->actingAsJwt($this->admin)->postJson('/api/v1/stock-opnames', [
            'opname_date' => '2026-08-27',
            'store_id' => $this->store->id,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'SO-OREN-POS-20260827-002')
            ->assertJsonPath('data.items.0.last_known_stock', 12);

        $nextId = $next->json('data.id');
        $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/stock-opnames/'.$nextId.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->actingAsJwt($this->admin)
            ->deleteJson('/api/v1/stock-opnames/'.$nextId)
            ->assertOk();
        $this->assertSoftDeleted('stock_opnames', ['id' => $nextId]);

        $afterDeleted = $this->actingAsJwt($this->admin)->postJson('/api/v1/stock-opnames', [
            'opname_date' => '2026-08-27',
            'store_id' => $this->store->id,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'SO-OREN-POS-20260827-003');

        $this->assertSame(3, $afterDeleted->json('data.sequence_number'));
    }

    public function test_pending_conflicts_and_reject_are_enforced(): void
    {
        $first = $this->createOpname($this->admin);

        $this->actingAsJwt($this->admin)->postJson('/api/v1/stock-opnames', [
            'opname_date' => '2026-08-28',
            'store_id' => $this->store->id,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Toko ini sudah memiliki opname yang masih pending. Selesaikan atau batalkan terlebih dahulu.')
            ->assertJsonPath('errors', null);

        $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/stock-opnames/'.$first->json('data.id').'/reject')
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_foreign_tenant_ids_and_records_are_not_accessible(): void
    {
        [$foreignCompany, $foreignStore, $foreignCategory] = $this->foreignTenant();
        $foreignAdmin = User::factory()->create([
            'role_id' => $this->admin->role_id,
            'company_id' => $foreignCompany->id,
        ]);
        $foreignOpname = $this->createOpname($foreignAdmin, $foreignStore, $foreignCategory);

        $this->actingAsJwt($this->admin)->postJson('/api/v1/stock-opnames', [
            'opname_date' => '2026-08-27',
            'store_id' => $foreignStore->id,
            'product_category_id' => $foreignCategory->id,
        ])->assertUnprocessable();

        $this->actingAsJwt($this->admin)
            ->getJson('/api/v1/stock-opnames/'.$foreignOpname->json('data.id'))
            ->assertNotFound();

        $this->actingAsJwt($this->admin)
            ->getJson('/api/v1/stock-opnames?store_id='.$foreignStore->id)
            ->assertOk()
            ->assertJsonCount(0, 'data.data');
    }

    public function test_product_api_rejects_foreign_store_and_category_ids(): void
    {
        [, $foreignStore, $foreignCategory] = $this->foreignTenant();

        $this->actingAsJwt($this->admin)->postJson('/api/v1/products', [
            'name' => 'Foreign Product',
            'code' => 'FOREIGN-PRODUCT',
            'store_id' => $foreignStore->id,
            'product_category_id' => $foreignCategory->id,
            'selling_type' => 'Purchase',
            'price' => 1000,
        ])->assertUnprocessable();

        $this->actingAsJwt($this->admin)->putJson('/api/v1/products/'.$this->product->id, [
            'store_id' => $foreignStore->id,
            'product_category_id' => $foreignCategory->id,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('products', ['code' => 'FOREIGN-PRODUCT']);
        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'store_id' => $this->store->id,
            'product_category_id' => $this->category->id,
        ]);
    }

    public function test_product_repository_rejects_foreign_relations_on_create_and_update(): void
    {
        [, $foreignStore, $foreignCategory] = $this->foreignTenant();
        $this->authenticateRepositoryAs($this->admin);
        $repository = app(ProductRepository::class);

        $this->assertThrows(
            fn() => $repository->create([
                'name' => 'Foreign Repository Product',
                'code' => 'FOREIGN-REPOSITORY-PRODUCT',
                'store_id' => $foreignStore->id,
                'product_category_id' => $foreignCategory->id,
                'selling_type' => 'Purchase',
                'price' => 1000,
            ]),
            ModelNotFoundException::class,
        );
        $this->assertThrows(
            fn() => $repository->update($this->product->id, [
                'store_id' => $foreignStore->id,
                'product_category_id' => $foreignCategory->id,
            ]),
            ModelNotFoundException::class,
        );

        $this->assertDatabaseMissing('products', ['code' => 'FOREIGN-REPOSITORY-PRODUCT']);
        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'store_id' => $this->store->id,
            'product_category_id' => $this->category->id,
        ]);
    }

    public function test_staff_without_store_id_uses_assigned_store_and_scopes_products(): void
    {
        $otherStore = Store::create([
            'name' => 'Toko Dua',
            'company_id' => $this->company->id,
        ]);
        $otherProduct = Product::create([
            'name' => 'Minyak',
            'code' => 'MINYAK-'.$this->company->id,
            'store_id' => $otherStore->id,
            'product_category_id' => $this->category->id,
            'selling_type' => 'Purchase',
            'price' => 20000,
        ]);

        $created = $this->actingAsJwt($this->staff)->postJson('/api/v1/stock-opnames', [
            'opname_date' => '2026-08-27',
        ])->assertCreated()
            ->assertJsonPath('data.store_id', $this->store->id)
            ->assertJsonCount(1, 'data.items');

        $this->assertNotSame($otherProduct->id, $created->json('data.items.0.product_id'));
    }

    public function test_staff_explicitly_requesting_another_store_is_rejected(): void
    {
        $otherStore = Store::create([
            'name' => 'Toko Dua',
            'company_id' => $this->company->id,
        ]);

        $this->actingAsJwt($this->staff)->postJson('/api/v1/stock-opnames', [
            'opname_date' => '2026-08-27',
            'store_id' => $otherStore->id,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('stock_opnames', 0);
    }

    public function test_staff_cannot_use_a_category_without_products_in_the_assigned_store(): void
    {
        $otherStore = Store::create([
            'name' => 'Toko Dua',
            'company_id' => $this->company->id,
        ]);
        $otherCategory = ProductCategory::create([
            'name' => 'Bahan Toko Dua',
            'slug' => 'bahan-toko-dua',
            'company_id' => $this->company->id,
        ]);
        Product::create([
            'name' => 'Minyak',
            'code' => 'MINYAK-TOKO-DUA',
            'store_id' => $otherStore->id,
            'product_category_id' => $otherCategory->id,
            'selling_type' => 'Purchase',
            'price' => 20000,
        ]);

        $this->actingAsJwt($this->staff)->postJson('/api/v1/stock-opnames', [
            'opname_date' => '2026-08-27',
            'product_category_id' => $otherCategory->id,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('stock_opnames', 0);
    }

    public function test_staff_admin_actions_are_forbidden_but_cancelled_own_opname_can_be_deleted(): void
    {
        $created = $this->createOpname($this->staff);
        $id = $created->json('data.id');

        $this->actingAsJwt($this->staff)
            ->postJson('/api/v1/stock-opnames/'.$id.'/approve')
            ->assertForbidden();
        $this->actingAsJwt($this->staff)
            ->postJson('/api/v1/stock-opnames/'.$id.'/reject')
            ->assertForbidden();
        $this->actingAsJwt($this->staff)
            ->postJson('/api/v1/stock-opnames/'.$id.'/cancel')
            ->assertOk();
        $this->actingAsJwt($this->staff)
            ->deleteJson('/api/v1/stock-opnames/'.$id)
            ->assertOk();
        $this->actingAsJwt($this->staff)
            ->postJson('/api/v1/products', [])
            ->assertForbidden();
        $this->actingAsJwt($this->staff)
            ->getJson('/api/v1/reports/sales-summary')
            ->assertForbidden();
        $this->actingAsJwt($this->staff)
            ->getJson('/api/v1/export/transactions')
            ->assertForbidden();
    }

    public function test_owner_with_company_id_cannot_access_any_stock_opname_endpoint(): void
    {
        $created = $this->createOpname($this->admin);
        $opnameId = $created->json('data.id');
        $itemId = $created->json('data.items.0.id');
        $owner = $this->owner();

        foreach ([
            ['GET', '/api/v1/stock-opnames', []],
            ['POST', '/api/v1/stock-opnames', ['opname_date' => '2026-08-27']],
            ['GET', '/api/v1/stock-opnames/'.$opnameId, []],
            ['PUT', '/api/v1/stock-opnames/'.$opnameId, ['notes' => 'owner']],
            ['PUT', '/api/v1/stock-opnames/'.$opnameId.'/items/'.$itemId, ['counted_stock' => 1]],
            ['POST', '/api/v1/stock-opnames/'.$opnameId.'/cancel', []],
            ['POST', '/api/v1/stock-opnames/'.$opnameId.'/approve', []],
            ['POST', '/api/v1/stock-opnames/'.$opnameId.'/reject', []],
            ['DELETE', '/api/v1/stock-opnames/'.$opnameId, []],
        ] as [$method, $uri, $data]) {
            $this->actingAsJwt($owner)->json($method, $uri, $data)->assertForbidden();
        }
    }

    public function test_jwt_rejects_owner_claim_when_persisted_role_is_admin(): void
    {
        $user = $this->owner();
        $token = JWTAuth::fromUser($user);
        $user->update(['role_id' => $this->admin->role_id]);

        $this->withToken($token)
            ->getJson('/api/v1/stock-opnames')
            ->assertUnauthorized();
    }

    public function test_jwt_rejects_persisted_owner_when_claim_role_is_admin(): void
    {
        $user = User::factory()->create([
            'role_id' => $this->admin->role_id,
            'company_id' => $this->company->id,
        ]);
        $token = JWTAuth::fromUser($user);
        $user->update(['role_id' => Role::firstOrCreate(['name' => 'owner'])->id]);

        $this->withToken($token)
            ->getJson('/api/v1/stock-opnames')
            ->assertUnauthorized();
    }

    public function test_protected_routes_reject_a_stale_owner_role_claim(): void
    {
        $user = $this->owner();
        $token = JWTAuth::fromUser($user);
        $user->update(['role_id' => $this->admin->role_id]);

        $this->withToken($token)
            ->getJson('/api/v1/dashboard/summary')
            ->assertUnauthorized();
    }

    public function test_protected_routes_reject_a_stale_company_claim(): void
    {
        [$foreignCompany] = $this->foreignTenant();
        $token = JWTAuth::fromUser($this->admin);
        $this->admin->update(['company_id' => $foreignCompany->id]);

        $this->withToken($token)
            ->getJson('/api/v1/products')
            ->assertUnauthorized();
    }

    public function test_jwt_only_routes_reject_stale_identity_claims(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $this->admin->update(['role_id' => $this->staff->role_id]);

        $this->withToken($token)
            ->getJson('/api/v1/auth/user')
            ->assertUnauthorized();
    }

    public function test_unassigned_staff_cannot_read_mutate_or_create_stock_opnames(): void
    {
        $created = $this->createOpname($this->admin);
        $id = $created->json('data.id');
        $itemId = $created->json('data.items.0.id');
        $staff = User::factory()->create([
            'role_id' => $this->staff->role_id,
            'company_id' => $this->company->id,
            'store_id' => null,
        ]);

        $this->actingAsJwt($staff)
            ->getJson('/api/v1/stock-opnames')
            ->assertOk()
            ->assertJsonCount(0, 'data.data');
        $this->actingAsJwt($staff)
            ->getJson('/api/v1/stock-opnames/'.$id)
            ->assertNotFound();
        $this->actingAsJwt($staff)
            ->putJson('/api/v1/stock-opnames/'.$id.'/items/'.$itemId, ['counted_stock' => 2])
            ->assertUnprocessable();
        $this->actingAsJwt($staff)
            ->postJson('/api/v1/stock-opnames/'.$id.'/cancel')
            ->assertUnprocessable();
        $this->actingAsJwt($staff)
            ->postJson('/api/v1/stock-opnames', ['opname_date' => '2026-08-28'])
            ->assertForbidden();
    }

    public function test_stale_store_claim_cannot_access_either_old_or_new_store(): void
    {
        [$otherStore, $otherProduct] = $this->otherStoreProduct();
        $first = $this->createOpname($this->admin);
        $second = $this->createOpname($this->admin, $otherStore);
        $token = JWTAuth::fromUser($this->staff);
        $this->staff->update(['store_id' => $otherStore->id]);

        $this->withToken($token)
            ->getJson('/api/v1/stock-opnames')
            ->assertUnauthorized();

        foreach ([$first, $second] as $created) {
            $id = $created->json('data.id');
            $itemId = $created->json('data.items.0.id');

            $this->withToken($token)->getJson('/api/v1/stock-opnames/'.$id)->assertUnauthorized();
            $this->withToken($token)
                ->putJson('/api/v1/stock-opnames/'.$id.'/items/'.$itemId, ['counted_stock' => 2])
                ->assertUnauthorized();
            $this->withToken($token)
                ->postJson('/api/v1/stock-opnames/'.$id.'/cancel')
                ->assertUnauthorized();
        }

        $this->assertDatabaseHas('products', ['id' => $otherProduct->id, 'store_id' => $otherStore->id]);
    }

    public function test_stale_role_claims_fail_closed_in_both_directions(): void
    {
        $created = $this->createOpname($this->admin);
        $id = $created->json('data.id');
        $itemId = $created->json('data.items.0.id');

        $adminToStaff = User::factory()->create([
            'role_id' => $this->admin->role_id,
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);
        $adminToken = JWTAuth::fromUser($adminToStaff);
        $adminToStaff->update(['role_id' => $this->staff->role_id]);

        $staffToAdmin = User::factory()->create([
            'role_id' => $this->staff->role_id,
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);
        $staffToken = JWTAuth::fromUser($staffToAdmin);
        $staffToAdmin->update(['role_id' => $this->admin->role_id]);

        foreach ([$adminToken, $staffToken] as $token) {
            $this->withToken($token)
                ->getJson('/api/v1/stock-opnames')
                ->assertUnauthorized();
            $this->withToken($token)
                ->getJson('/api/v1/stock-opnames/'.$id)
                ->assertUnauthorized();
            $this->withToken($token)
                ->putJson('/api/v1/stock-opnames/'.$id.'/items/'.$itemId, ['counted_stock' => 2])
                ->assertUnauthorized();
            $this->withToken($token)
                ->postJson('/api/v1/stock-opnames/'.$id.'/cancel')
                ->assertUnauthorized();
            $this->withToken($token)
                ->postJson('/api/v1/stock-opnames', ['opname_date' => '2026-08-28'])
                ->assertUnauthorized();
        }
    }

    public function test_null_role_claim_fails_closed_after_staff_assignment(): void
    {
        $user = User::factory()->create([
            'role_id' => null,
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);
        $token = JWTAuth::fromUser($user);
        $user->update(['role_id' => $this->staff->role_id]);

        $this->withToken($token)
            ->getJson('/api/v1/stock-opnames')
            ->assertUnauthorized();
        $this->withToken($token)
            ->postJson('/api/v1/stock-opnames', ['opname_date' => '2026-08-28'])
            ->assertUnauthorized();
    }

    public function test_approval_requires_every_item_to_be_counted(): void
    {
        $created = $this->createOpname($this->admin);
        $id = $created->json('data.id');

        $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/stock-opnames/'.$id.'/approve')
            ->assertUnprocessable();

        $this->assertDatabaseHas('stock_opnames', ['id' => $id, 'status' => 'pending']);
    }

    public function test_only_the_first_pending_transition_succeeds_and_late_updates_fail(): void
    {
        $created = $this->createOpname($this->admin);
        $id = $created->json('data.id');
        $itemId = $created->json('data.items.0.id');

        $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/stock-opnames/'.$id.'/reject')
            ->assertOk();

        $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/stock-opnames/'.$id.'/cancel')
            ->assertUnprocessable();
        $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/stock-opnames/'.$id.'/approve')
            ->assertUnprocessable();
        $this->actingAsJwt($this->admin)
            ->putJson('/api/v1/stock-opnames/'.$id, ['notes' => 'too late'])
            ->assertUnprocessable();
        $this->actingAsJwt($this->admin)
            ->putJson('/api/v1/stock-opnames/'.$id.'/items/'.$itemId, ['counted_stock' => 1])
            ->assertUnprocessable();
        $this->actingAsJwt($this->admin)
            ->deleteJson('/api/v1/stock-opnames/'.$id)
            ->assertUnprocessable();

        $this->assertDatabaseHas('stock_opnames', ['id' => $id, 'status' => 'rejected']);
        $this->assertDatabaseHas('stock_opname_items', ['id' => $itemId, 'counted_stock' => null]);
    }

    public function test_cancelled_opname_can_only_be_deleted_once(): void
    {
        $created = $this->createOpname($this->admin);
        $id = $created->json('data.id');

        $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/stock-opnames/'.$id.'/cancel')
            ->assertOk();
        $this->actingAsJwt($this->admin)
            ->deleteJson('/api/v1/stock-opnames/'.$id)
            ->assertOk();
        $this->actingAsJwt($this->admin)
            ->deleteJson('/api/v1/stock-opnames/'.$id)
            ->assertUnprocessable();
    }

    public function test_empty_product_selection_rolls_back_creation_and_sequence(): void
    {
        $emptyCategory = ProductCategory::create([
            'name' => 'Kosong',
            'slug' => 'kosong',
            'company_id' => $this->company->id,
        ]);

        $this->actingAsJwt($this->admin)->postJson('/api/v1/stock-opnames', [
            'opname_date' => '2026-08-27',
            'store_id' => $this->store->id,
            'product_category_id' => $emptyCategory->id,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Tidak ada produk pembelian yang ditemukan untuk filter yang dipilih.')
            ->assertJsonPath('errors', null);

        $this->assertDatabaseCount('stock_opnames', 0);
        $this->createOpname($this->admin)->assertJsonPath('data.code', 'SO-OREN-POS-20260827-001');
    }

    public function test_last_stock_for_multiple_products_uses_one_bulk_lookup(): void
    {
        $products = collect([$this->product]);
        foreach ([['Bawang', 7000], ['Garam', 3000]] as [$name, $price]) {
            $products->push(Product::create([
                'name' => $name,
                'code' => strtoupper($name).'-'.$this->company->id,
                'store_id' => $this->store->id,
                'product_category_id' => $this->category->id,
                'selling_type' => 'Purchase',
                'price' => $price,
            ]));
        }

        $approved = StockOpname::create([
            'code' => 'SO-LOOKUP-HISTORY',
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'started_by' => $this->admin->id,
            'opname_date' => '2026-08-26',
            'sequence_number' => 1,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);
        $approved->items()->createMany($products->values()->map(fn(Product $product, int $index) => [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'last_known_stock' => 0,
            'counted_stock' => ($index + 1) * 5,
            'variance' => ($index + 1) * 5,
        ])->all());

        DB::flushQueryLog();
        DB::enableQueryLog();
        $created = $this->createOpname($this->admin)->assertJsonCount(3, 'data.items');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $bulkQueries = array_filter($queries, fn(array $query) =>
            str_contains(strtolower($query['query']), 'stock_opname_items') &&
            str_contains(strtolower($query['query']), 'join') &&
            str_contains(strtolower($query['query']), 'stock_opnames')
        );
        $this->assertCount(1, $bulkQueries);
        $this->assertEquals(
            [5, 10, 15],
            collect($created->json('data.items'))->sortBy('product_name')->pluck('last_known_stock')->sort()->values()->all()
        );
    }

    public function test_company_date_sequence_has_a_unique_database_constraint(): void
    {
        $this->createOpname($this->admin);

        $this->expectException(QueryException::class);
        StockOpname::create([
            'code' => 'SO-DUPLICATE',
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'started_by' => $this->admin->id,
            'opname_date' => '2026-08-27',
            'sequence_number' => 1,
            'status' => 'pending',
        ]);
    }

    public function test_global_role_writes_are_owner_only_while_staff_can_list_roles(): void
    {
        $customRole = Role::create(['name' => 'cashier']);

        $this->actingAsJwt($this->staff)
            ->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => 'staff']);
        $this->actingAsJwt($this->staff)
            ->getJson('/api/v1/roles/'.$customRole->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'cashier');

        foreach ([
            ['POST', '/api/v1/roles', ['name' => 'supervisor']],
            ['PUT', '/api/v1/roles/'.$customRole->id, ['name' => 'cashier-put']],
            ['PATCH', '/api/v1/roles/'.$customRole->id, ['name' => 'cashier-patch']],
            ['DELETE', '/api/v1/roles/'.$customRole->id, []],
        ] as [$method, $uri, $data]) {
            $this->actingAsJwt($this->admin)->json($method, $uri, $data)->assertForbidden();
        }

        $owner = $this->owner();
        $this->actingAsJwt($owner)
            ->postJson('/api/v1/roles', ['name' => 'supervisor'])
            ->assertOk()
            ->assertJsonPath('data.name', 'supervisor');
        $this->actingAsJwt($owner)
            ->putJson('/api/v1/roles/'.$customRole->id, ['name' => 'cashier-put'])
            ->assertOk()
            ->assertJsonPath('data.name', 'cashier-put');
        $this->actingAsJwt($owner)
            ->patchJson('/api/v1/roles/'.$customRole->id, ['name' => 'cashier-patch'])
            ->assertOk()
            ->assertJsonPath('data.name', 'cashier-patch');
        $this->actingAsJwt($owner)
            ->deleteJson('/api/v1/roles/'.$customRole->id)
            ->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $customRole->id]);
    }

    public function test_tenant_admin_cannot_assign_the_owner_role(): void
    {
        $ownerRole = Role::firstOrCreate(['name' => 'owner']);

        $this->actingAsJwt($this->admin)->postJson('/api/v1/users', [
            'name' => 'Escalated User',
            'email' => 'escalated@example.com',
            'password' => 'secret123',
            'role_id' => $ownerRole->id,
            'store_id' => $this->store->id,
        ])->assertUnprocessable();

        $this->actingAsJwt($this->admin)->putJson('/api/v1/users/'.$this->staff->id, [
            'role_id' => $ownerRole->id,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('users', ['email' => 'escalated@example.com']);
        $this->assertDatabaseHas('users', [
            'id' => $this->staff->id,
            'role_id' => $this->staff->role_id,
        ]);
    }

    public function test_tenant_admin_cannot_assign_a_store_from_another_company(): void
    {
        [, $foreignStore] = $this->foreignTenant();

        $this->actingAsJwt($this->admin)->postJson('/api/v1/users', [
            'name' => 'Foreign Store User',
            'email' => 'foreign-store@example.com',
            'password' => 'secret123',
            'role_id' => $this->staff->role_id,
            'store_id' => $foreignStore->id,
        ])->assertUnprocessable();

        $this->actingAsJwt($this->admin)->putJson('/api/v1/users/'.$this->staff->id, [
            'store_id' => $foreignStore->id,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('users', ['email' => 'foreign-store@example.com']);
        $this->assertDatabaseHas('users', [
            'id' => $this->staff->id,
            'store_id' => $this->store->id,
        ]);
    }

    public function test_tenant_admin_user_list_excludes_same_company_owners(): void
    {
        $owner = $this->owner();

        $this->actingAsJwt($this->admin)
            ->getJson('/api/v1/users?per_page=100')
            ->assertOk()
            ->assertJsonFragment(['id' => $this->staff->id])
            ->assertJsonMissing(['id' => $owner->id]);
    }

    public function test_tenant_admin_cannot_show_a_same_company_owner(): void
    {
        $owner = $this->owner();

        $this->actingAsJwt($this->admin)
            ->getJson('/api/v1/users/'.$owner->id)
            ->assertNotFound();
    }

    public function test_tenant_admin_cannot_update_a_same_company_owner(): void
    {
        $owner = $this->owner();
        $originalName = $owner->name;

        $this->actingAsJwt($this->admin)
            ->putJson('/api/v1/users/'.$owner->id, ['name' => 'Tenant takeover'])
            ->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'name' => $originalName,
        ]);
    }

    public function test_tenant_admin_cannot_delete_a_same_company_owner(): void
    {
        $owner = $this->owner();

        $this->actingAsJwt($this->admin)
            ->deleteJson('/api/v1/users/'.$owner->id)
            ->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_transactions_reject_products_outside_the_actor_tenant_and_store(): void
    {
        [, $foreignStore] = $this->foreignTenant();
        $foreignProduct = Product::where('store_id', $foreignStore->id)->firstOrFail();
        [, $otherProduct] = $this->otherStoreProduct();
        $payload = [
            'date' => '2026-08-27',
            'type' => 'OFFLINE',
            'payment_type' => 'CASH',
            'status' => 'PENDING',
            'items' => [[
                'product_id' => $foreignProduct->id,
                'qty' => 1,
                'price' => 5000,
            ]],
        ];

        $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/transactions', $payload)
            ->assertUnprocessable();

        $payload['items'][0]['product_id'] = $otherProduct->id;
        $this->actingAsJwt($this->staff)
            ->postJson('/api/v1/transactions', $payload)
            ->assertUnprocessable();

        $this->staff->update(['store_id' => null]);
        $payload['items'][0]['product_id'] = $this->product->id;
        $this->actingAsJwt($this->staff)
            ->postJson('/api/v1/transactions', $payload)
            ->assertUnprocessable();

        $payload['items'][0]['product_id'] = $this->product->id;
        $created = $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/transactions', $payload)
            ->assertCreated();
        $transactionId = $created->json('data.id');

        $payload['items'][0]['product_id'] = $foreignProduct->id;
        $this->actingAsJwt($this->admin)
            ->putJson('/api/v1/transactions/'.$transactionId, $payload)
            ->assertUnprocessable();

        $this->assertDatabaseHas('transaction_items', [
            'transaction_id' => $transactionId,
            'product_id' => $this->product->id,
        ]);
        $this->assertDatabaseMissing('transaction_items', [
            'transaction_id' => $transactionId,
            'product_id' => $foreignProduct->id,
        ]);
    }

    public function test_transaction_repository_rejects_foreign_products_before_mutation(): void
    {
        [, $foreignStore] = $this->foreignTenant();
        $foreignProduct = Product::where('store_id', $foreignStore->id)->firstOrFail();
        $payload = [
            'date' => '2026-08-27',
            'type' => 'OFFLINE',
            'payment_type' => 'CASH',
            'status' => 'PENDING',
            'items' => [[
                'product_id' => $this->product->id,
                'qty' => 1,
                'price' => 10000,
            ]],
        ];
        $this->authenticateRepositoryAs($this->admin);
        $repository = app(TransactionRepository::class);
        $transaction = $repository->create($payload);

        $payload['items'][0]['product_id'] = $foreignProduct->id;

        try {
            $repository->update($transaction->id, $payload);
            $this->fail('Expected foreign product update to be rejected.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseHas('transaction_items', [
                'transaction_id' => $transaction->id,
                'product_id' => $this->product->id,
            ]);
            $this->assertDatabaseMissing('transaction_items', [
                'transaction_id' => $transaction->id,
                'product_id' => $foreignProduct->id,
            ]);
        }

        $this->expectException(ModelNotFoundException::class);
        $repository->create($payload);
    }

    public function test_transaction_repository_normalizes_product_ids_and_rejects_malformed_items(): void
    {
        $this->authenticateRepositoryAs($this->admin);
        $repository = app(TransactionRepository::class);
        $payload = [
            'date' => '2026-08-27',
            'type' => 'OFFLINE',
            'payment_type' => 'CASH',
            'status' => 'PENDING',
            'items' => [[
                'product_id' => strtoupper($this->product->id),
                'qty' => 1,
                'price' => 10000,
            ]],
        ];
        $transaction = $repository->create($payload);

        $this->assertDatabaseHas('transaction_items', [
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
        ]);

        foreach ([[], [['qty' => 1, 'price' => 10000]]] as $items) {
            try {
                $repository->update($transaction->id, ['items' => $items]);
                $this->fail('Expected malformed transaction items to be rejected.');
            } catch (ModelNotFoundException) {
                $this->assertDatabaseHas('transaction_items', [
                    'transaction_id' => $transaction->id,
                    'product_id' => $this->product->id,
                ]);
            }
        }
    }

    public function test_transaction_item_replacement_is_atomic_and_schema_bounded(): void
    {
        $payload = [
            'date' => '2026-08-27',
            'type' => 'OFFLINE',
            'payment_type' => 'CASH',
            'status' => 'PENDING',
            'items' => [[
                'product_id' => $this->product->id,
                'qty' => 1,
                'price' => 10000,
            ]],
        ];
        $created = $this->actingAsJwt($this->admin)
            ->postJson('/api/v1/transactions', $payload)
            ->assertCreated();
        $transactionId = $created->json('data.id');

        $payload['items'][0]['qty'] = 2;
        $payload['items'][0]['price'] = 7000;
        $this->actingAsJwt($this->admin)
            ->putJson('/api/v1/transactions/'.$transactionId, $payload)
            ->assertOk()
            ->assertJsonPath('data.total_item', 2)
            ->assertJsonPath('data.total', '14000.00');
        $this->assertDatabaseHas('transaction_items', [
            'transaction_id' => $transactionId,
            'product_id' => $this->product->id,
            'qty' => 2,
            'total_price' => 14000,
        ]);

        foreach ([
            ['qty' => 2147483648, 'price' => 1],
            ['qty' => 1, 'price' => 10000000000000],
            ['qty' => 1, 'price' => '999999999999999999999999999999'],
            ['qty' => 2, 'price' => 9999999999999.99],
            ['qty' => 2, 'price' => '+9999999999999.99'],
        ] as $values) {
            $payload['items'][0] = ['product_id' => $this->product->id] + $values;
            $this->actingAsJwt($this->admin)
                ->putJson('/api/v1/transactions/'.$transactionId, $payload)
                ->assertUnprocessable();
        }

        foreach ([
            [
                ['product_id' => $this->product->id, 'qty' => 1, 'price' => 6000000000000],
                ['product_id' => $this->product->id, 'qty' => 1, 'price' => 6000000000000],
            ],
            [
                ['product_id' => $this->product->id, 'qty' => 1073741824, 'price' => 0],
                ['product_id' => $this->product->id, 'qty' => 1073741824, 'price' => 0],
            ],
            ['custom-key' => ['product_id' => $this->product->id, 'qty' => 1, 'price' => 1]],
            [['product_id' => $this->product->id, 'qty' => 1, 'price' => []]],
        ] as $items) {
            $payload['items'] = $items;
            $this->actingAsJwt($this->admin)
                ->putJson('/api/v1/transactions/'.$transactionId, $payload)
                ->assertUnprocessable();
        }

        $this->authenticateRepositoryAs($this->admin);
        $repository = app(TransactionRepository::class);
        try {
            $repository->update($transactionId, [
                'date' => '2026-08-28',
                'items' => [[
                    'product_id' => $this->product->id,
                    'qty' => [],
                    'price' => 10000,
                ]],
            ]);
            $this->fail('Expected invalid item replacement to fail.');
        } catch (\Throwable) {
            $this->assertDatabaseHas('transactions', [
                'id' => $transactionId,
                'date' => '2026-08-27 00:00:00',
            ]);
            $this->assertDatabaseHas('transaction_items', [
                'transaction_id' => $transactionId,
                'product_id' => $this->product->id,
            ]);
        }
    }

    public function test_reporting_and_exports_require_admin_and_their_plan_features(): void
    {
        $this->actingAsJwt($this->staff)
            ->getJson('/api/v1/reports/sales-summary')
            ->assertForbidden();
        $this->actingAsJwt($this->staff)
            ->getJson('/api/v1/export/transactions')
            ->assertForbidden();

        $this->actingAsJwt($this->admin)
            ->getJson('/api/v1/reports/sales-summary')
            ->assertForbidden()
            ->assertJsonPath('errors.feature', 'reporting');
        $this->actingAsJwt($this->admin)
            ->getJson('/api/v1/export/transactions')
            ->assertForbidden()
            ->assertJsonPath('errors.feature', 'export-transaction');

        foreach (['reporting', 'export-transaction'] as $index => $slug) {
            $feature = Feature::create([
                'slug' => $slug,
                'name' => $slug,
                'route' => '/'.$slug,
                'sort_order' => 20 + $index,
            ]);
            PlanFeature::create([
                'plan' => 'pro',
                'feature_id' => $feature->id,
                'is_active' => true,
            ]);
        }

        $this->actingAsJwt($this->admin)
            ->getJson('/api/v1/reports/sales-summary')
            ->assertOk();
        $this->actingAsJwt($this->admin)
            ->get('/api/v1/export/stores')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_dashboard_management_is_admin_only(): void
    {
        $this->actingAsJwt($this->staff)
            ->getJson('/api/v1/dashboard/summary')
            ->assertForbidden();
    }

    public function test_wifi_credentials_support_patch_updates(): void
    {
        $credential = WifiCredential::create([
            'code' => 'OLD-WIFI',
            'is_active' => true,
            'company_id' => $this->company->id,
        ]);

        $this->actingAsJwt($this->admin)
            ->patchJson('/api/v1/wifi-credentials/'.$credential->id, [
                'code' => 'NEW-WIFI',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.code', 'NEW-WIFI');

        $this->assertDatabaseHas('wifi_credentials', [
            'id' => $credential->id,
            'code' => 'NEW-WIFI',
            'is_active' => false,
        ]);
        $this->options('/api/v1/wifi-credentials/'.$credential->id)
            ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
    }

    public function test_sqlite_alignment_migration_rolls_back_and_migrates_existing_values(): void
    {
        foreach ([
            'pending' => 1,
            'approved' => 2,
            'rejected' => 3,
            'cancelled' => 4,
        ] as $status => $sequence) {
            StockOpname::create([
                'code' => 'SO-MIGRATION-'.$sequence,
                'company_id' => $this->company->id,
                'store_id' => $this->store->id,
                'started_by' => $this->admin->id,
                'opname_date' => '2026-08-26',
                'sequence_number' => $sequence,
                'status' => $status,
            ]);
        }

        $migration = require database_path('migrations/2026_08_27_000000_align_sqlite_product_selling_type.php');
        $migration->down();

        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'selling_type' => 'Ingredient']);
        $this->assertDatabaseHas('stock_opnames', ['code' => 'SO-MIGRATION-1', 'status' => 'in_progress']);
        $this->assertDatabaseHas('stock_opnames', ['code' => 'SO-MIGRATION-2', 'status' => 'completed']);
        $this->assertDatabaseHas('stock_opnames', ['code' => 'SO-MIGRATION-3', 'status' => 'draft']);
        $this->assertDatabaseHas('stock_opnames', ['code' => 'SO-MIGRATION-4', 'status' => 'draft']);

        $migration->up();

        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'selling_type' => 'Purchase']);
        $this->assertDatabaseHas('stock_opnames', ['code' => 'SO-MIGRATION-1', 'status' => 'pending']);
        $this->assertDatabaseHas('stock_opnames', ['code' => 'SO-MIGRATION-2', 'status' => 'approved']);
        $this->assertDatabaseHas('stock_opnames', ['code' => 'SO-MIGRATION-3', 'status' => 'pending']);
        $this->assertDatabaseHas('stock_opnames', ['code' => 'SO-MIGRATION-4', 'status' => 'pending']);
    }

    public function test_user_features_keep_the_contract_and_filter_by_plan_and_role(): void
    {
        foreach ([
            ['slug' => 'dashboard', 'route' => '/dashboard', 'sort_order' => 0],
            ['slug' => 'pos', 'route' => '/pos', 'sort_order' => 1],
            ['slug' => 'reporting', 'route' => '/reporting', 'sort_order' => 2],
        ] as $data) {
            $feature = Feature::create($data + ['name' => $data['slug']]);
            PlanFeature::create([
                'plan' => 'pro',
                'feature_id' => $feature->id,
                'is_active' => true,
            ]);
        }

        $this->actingAsJwt($this->staff)
            ->getJson('/api/v1/user/features')
            ->assertOk()
            ->assertJsonPath('message', 'User features fetched')
            ->assertJsonMissing(['slug' => 'dashboard'])
            ->assertJsonMissing(['slug' => 'reporting'])
            ->assertJsonFragment(['slug' => 'pos', 'route' => '/pos']);

        $this->actingAsJwt($this->admin)
            ->getJson('/api/v1/user/features')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'reporting', 'route' => '/reporting']);
    }

    private function actingAsJwt(User $user): static
    {
        return $this->withToken(JWTAuth::fromUser($user));
    }

    private function authenticateRepositoryAs(User $user): void
    {
        JwtClaims::flush();
        request()->headers->set('Authorization', 'Bearer '.JWTAuth::fromUser($user));
        JWTAuth::parseToken()->authenticate();
    }

    private function owner(): User
    {
        return User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'owner'])->id,
            'company_id' => $this->company->id,
        ]);
    }

    private function createOpname(
        User $user,
        ?Store $store = null,
        ?ProductCategory $category = null,
    ) {
        return $this->actingAsJwt($user)->postJson('/api/v1/stock-opnames', [
            'opname_date' => '2026-08-27',
            'store_id' => ($store ?? $this->store)->id,
            'product_category_id' => ($category ?? $this->category)->id,
        ])->assertCreated();
    }

    private function otherStoreProduct(): array
    {
        $store = Store::create([
            'name' => 'Toko Dua',
            'company_id' => $this->company->id,
        ]);
        $product = Product::create([
            'name' => 'Minyak',
            'code' => 'MINYAK-'.$store->id,
            'store_id' => $store->id,
            'product_category_id' => $this->category->id,
            'selling_type' => 'Purchase',
            'price' => 20000,
        ]);

        return [$store, $product];
    }

    private function foreignTenant(): array
    {
        $company = Company::create([
            'name' => 'Lain',
            'slug' => 'lain',
            'plan' => 'pro',
        ]);
        $store = Store::create(['name' => 'Toko Lain', 'company_id' => $company->id]);
        $category = ProductCategory::create([
            'name' => 'Kategori Lain',
            'slug' => 'kategori-lain',
            'company_id' => $company->id,
        ]);
        Product::create([
            'name' => 'Produk Lain',
            'code' => 'PRODUK-LAIN',
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'selling_type' => 'Purchase',
            'price' => 5000,
        ]);

        return [$company, $store, $category];
    }
}
