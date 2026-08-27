<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DailyReport;
use App\Models\DailyReportCreditor;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Feature;
use App\Models\PlanFeature;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-23 10:00:00');

        $this->company = Company::create([
            'name' => 'Jajaneun',
            'slug' => 'jajaneun',
            'plan' => 'pro',
        ]);
        $this->store = Store::create([
            'name' => 'Store Utama',
            'company_id' => $this->company->id,
        ]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'company_id' => $this->company->id,
        ]);

        $feature = Feature::create([
            'slug' => 'daily-report',
            'name' => 'Laporan Harian',
            'route' => '/daily-report',
            'icon' => 'clipboard-list',
            'group' => 'keuangan',
            'sort_order' => 15,
        ]);
        PlanFeature::create([
            'plan' => 'pro',
            'feature_id' => $feature->id,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_can_create_a_store_scoped_creditor_with_opening_balance(): void
    {
        $response = $this->withToken(JWTAuth::fromUser($this->admin))
            ->postJson('/api/v1/daily-reports/creditors', [
                'store_id' => $this->store->id,
                'name' => 'Iqbal',
                'opening_balance' => 100000,
                'opening_date' => '2026-08-23',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Iqbal')
            ->assertJsonPath('data.opening_balance', '100000.00');

        $this->assertDatabaseHas('daily_report_creditors', [
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'name' => 'Iqbal',
            'opening_balance' => 100000,
        ]);
    }

    public function test_daily_report_is_blocked_when_the_plan_has_no_feature(): void
    {
        $this->company->update(['plan' => 'basic']);

        $this->withToken(JWTAuth::fromUser($this->admin))
            ->getJson('/api/v1/daily-reports/creditors?store_id='.$this->store->id)
            ->assertForbidden()
            ->assertJsonPath('errors.feature', 'daily-report');
    }

    public function test_empty_expense_categories_get_a_default_restock_category(): void
    {
        $feature = Feature::create([
            'slug' => 'expense-categories',
            'name' => 'Kategori Pengeluaran',
            'route' => '/expense-categories',
            'icon' => 'folder',
            'group' => 'keuangan',
            'sort_order' => 11,
        ]);
        PlanFeature::create([
            'plan' => 'pro',
            'feature_id' => $feature->id,
            'is_active' => true,
        ]);

        $this->withToken(JWTAuth::fromUser($this->admin))
            ->getJson('/api/v1/expense/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Restok');

        $this->assertDatabaseHas('expense_categories', [
            'company_id' => $this->company->id,
            'name' => 'Restok',
        ]);
    }

    public function test_saving_restock_creates_itemized_expenses_atomically(): void
    {
        $creditor = $this->creditor();
        $category = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Belanja Bahan',
            'code' => 'RESTOCK',
        ]);

        $response = $this->withToken(JWTAuth::fromUser($this->admin))
            ->putJson('/api/v1/daily-reports/2026-08-23/restock', [
                'store_id' => $this->store->id,
                'creditor_id' => $creditor->id,
                'items' => [
                    [
                        'name' => 'Ayam',
                        'quantity' => 300,
                        'unit' => 'tsk',
                        'expense_category_id' => $category->id,
                        'amount' => 210000,
                    ],
                    [
                        'name' => 'Koya',
                        'expense_category_id' => $category->id,
                        'amount' => 15000,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.restock.total', 225000)
            ->assertJsonCount(2, 'data.restock.items');

        $this->assertDatabaseCount('expenses', 2);
        $this->assertDatabaseHas('expenses', [
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'amount' => 210000,
            'reference' => 'Iqbal',
        ]);
    }

    public function test_debt_balance_is_opening_plus_restock_minus_payment(): void
    {
        $creditor = $this->creditor();
        $category = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Belanja Bahan',
            'code' => 'RESTOCK',
        ]);
        $token = JWTAuth::fromUser($this->admin);

        $this->withToken($token)->putJson('/api/v1/daily-reports/2026-08-23/restock', [
            'store_id' => $this->store->id,
            'creditor_id' => $creditor->id,
            'items' => [[
                'name' => 'Ayam dan Koya',
                'expense_category_id' => $category->id,
                'amount' => 225000,
            ]],
        ])->assertOk();

        $this->withToken($token)->putJson('/api/v1/daily-reports/2026-08-23/debt', [
            'store_id' => $this->store->id,
            'payments' => [[
                'creditor_id' => $creditor->id,
                'amount' => 25000,
            ]],
        ])->assertOk()
            ->assertJsonPath('data.debt.0.opening_balance', 100000)
            ->assertJsonPath('data.debt.0.restock', 225000)
            ->assertJsonPath('data.debt.0.payment', 25000)
            ->assertJsonPath('data.debt.0.closing_balance', 300000);
    }

    public function test_clearing_restock_removes_its_linked_expenses(): void
    {
        $creditor = $this->creditor();
        $category = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Belanja Bahan',
            'code' => 'RESTOCK',
        ]);
        $token = JWTAuth::fromUser($this->admin);
        $url = '/api/v1/daily-reports/2026-08-23/restock';

        $this->withToken($token)->putJson($url, [
            'store_id' => $this->store->id,
            'creditor_id' => $creditor->id,
            'items' => [[
                'name' => 'Koya',
                'expense_category_id' => $category->id,
                'amount' => 15000,
            ]],
        ])->assertOk();

        $this->withToken($token)->putJson($url, [
            'store_id' => $this->store->id,
            'creditor_id' => $creditor->id,
            'items' => [],
        ])->assertOk()
            ->assertJsonPath('data.restock.total', 0)
            ->assertJsonPath('data.restock.creditor', null);

        $this->assertSoftDeleted('expenses', ['amount' => 15000]);
    }

    public function test_income_uses_paid_transaction_items_for_the_selected_store_and_can_be_overridden(): void
    {
        $otherStore = Store::create([
            'name' => 'Store Lain',
            'company_id' => $this->company->id,
        ]);
        $category = ProductCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Makanan',
            'slug' => 'makanan',
            'status' => true,
        ]);
        $product = Product::create([
            'store_id' => $this->store->id,
            'product_category_id' => $category->id,
            'name' => 'Chili Oil',
            'code' => 'CO-1',
            'selling_type' => 'Sale',
            'price' => 12000,
            'status' => true,
        ]);

        $this->transactionItem($product, $this->store, 'OFFLINE', 'QRIS', 12000);
        $this->transactionItem($product, $this->store, 'SHOPEEFOOD', 'SHOPEEPAY', 244000);
        $this->transactionItem($product, $otherStore, 'OFFLINE', 'CASH', 999000);

        $response = $this->withToken(JWTAuth::fromUser($this->admin))
            ->putJson('/api/v1/daily-reports/2026-08-23/income', [
                'store_id' => $this->store->id,
                'amounts' => [
                    'shopeefood' => 244000,
                    'grabfood' => 0,
                    'gofood' => 0,
                    'qris' => 10000,
                    'cash' => 22000,
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.income.source.qris', 12000)
            ->assertJsonPath('data.income.source.shopeefood', 244000)
            ->assertJsonPath('data.income.reported.qris', 10000)
            ->assertJsonPath('data.income.reported.cash', 22000)
            ->assertJsonPath('data.income.total', 276000);
    }

    public function test_summary_uses_reported_income_with_pos_fallback_and_store_expenses(): void
    {
        $otherStore = Store::create([
            'name' => 'Store Lain',
            'company_id' => $this->company->id,
        ]);
        $productCategory = ProductCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Makanan',
            'slug' => 'makanan-summary',
            'status' => true,
        ]);
        $product = Product::create([
            'store_id' => $this->store->id,
            'product_category_id' => $productCategory->id,
            'name' => 'Produk Summary',
            'code' => 'SUMMARY-1',
            'selling_type' => 'Sale',
            'price' => 100000,
            'status' => true,
        ]);
        $expenseCategory = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Operasional',
            'code' => 'SUMMARY-EXPENSE',
        ]);

        $this->transactionItem($product, $this->store, 'OFFLINE', 'CASH', 100000, '2026-08-23');
        $this->transactionItem($product, $this->store, 'OFFLINE', 'CASH', 50000, '2026-08-22');
        $this->transactionItem($product, $otherStore, 'OFFLINE', 'CASH', 999000, '2026-08-22');

        Expense::create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'expense_category_id' => $expenseCategory->id,
            'date' => '2026-08-23',
            'amount' => 30000,
        ]);
        Expense::create([
            'company_id' => $this->company->id,
            'store_id' => $otherStore->id,
            'expense_category_id' => $expenseCategory->id,
            'date' => '2026-08-23',
            'amount' => 888000,
        ]);

        $token = JWTAuth::fromUser($this->admin);
        $this->withToken($token)
            ->putJson('/api/v1/daily-reports/2026-08-23/income', [
                'store_id' => $this->store->id,
                'amounts' => [
                    'shopeefood' => 0,
                    'grabfood' => 0,
                    'gofood' => 0,
                    'qris' => 0,
                    'cash' => 80000,
                ],
            ])->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/daily-reports/summary?store_id='.$this->store->id)
            ->assertOk()
            ->assertJsonPath('data.income', 130000)
            ->assertJsonPath('data.expense', 30000)
            ->assertJsonPath('data.balance', 100000);
    }

    public function test_history_is_paginated_by_report_date_for_infinite_scroll(): void
    {
        foreach (range(1, 16) as $day) {
            DailyReport::create([
                'company_id' => $this->company->id,
                'store_id' => $this->store->id,
                'report_date' => sprintf('2026-08-%02d', $day),
                'cash_amount' => $day * 1000,
                'qris_amount' => 0,
                'shopeefood_amount' => 0,
                'grabfood_amount' => 0,
                'gofood_amount' => 0,
            ]);
        }

        $response = $this->withToken(JWTAuth::fromUser($this->admin))
            ->getJson('/api/v1/daily-reports/history?tab=income&store_id='.$this->store->id.'&per_page=15&page=1');

        $response->assertOk()
            ->assertJsonCount(15, 'data.data')
            ->assertJsonPath('data.current_page', 1)
            ->assertJsonPath('data.last_page', 2)
            ->assertJsonPath('data.data.0.report_date', '2026-08-16');
    }

    public function test_staff_cannot_access_another_store_or_edit_past_dates(): void
    {
        $otherStore = Store::create([
            'name' => 'Store Lain',
            'company_id' => $this->company->id,
        ]);
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $staff = User::factory()->create([
            'role_id' => $staffRole->id,
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
        ]);
        $token = JWTAuth::fromUser($staff);

        $this->withToken($token)
            ->getJson('/api/v1/daily-reports/2026-08-23?store_id='.$otherStore->id)
            ->assertUnprocessable();

        $this->withToken($token)->putJson('/api/v1/daily-reports/2026-08-22/income', [
            'store_id' => $this->store->id,
            'amounts' => [
                'shopeefood' => 0,
                'grabfood' => 0,
                'gofood' => 0,
                'qris' => 0,
                'cash' => 0,
            ],
        ])->assertForbidden();
    }

    public function test_payment_cannot_exceed_available_debt(): void
    {
        $creditor = $this->creditor();

        $this->withToken(JWTAuth::fromUser($this->admin))
            ->putJson('/api/v1/daily-reports/2026-08-23/debt', [
                'store_id' => $this->store->id,
                'payments' => [[
                    'creditor_id' => $creditor->id,
                    'amount' => 100001,
                ]],
            ])->assertUnprocessable();
    }

    public function test_admin_can_list_and_deactivate_creditors_for_the_active_store(): void
    {
        $creditor = $this->creditor();
        $token = JWTAuth::fromUser($this->admin);

        $this->withToken($token)
            ->getJson('/api/v1/daily-reports/creditors?store_id='.$this->store->id)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Iqbal');

        $this->withToken($token)
            ->putJson('/api/v1/daily-reports/creditors/'.$creditor->id, [
                'store_id' => $this->store->id,
                'name' => 'Iqbal',
                'opening_balance' => 100000,
                'opening_date' => '2026-08-23',
                'is_active' => false,
            ])->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_daily_report_rejects_cross_tenant_store_references(): void
    {
        $otherCompany = Company::create([
            'name' => 'Tenant Lain',
            'slug' => 'tenant-lain',
            'plan' => 'pro',
        ]);
        $otherStore = Store::create([
            'name' => 'Store Tenant Lain',
            'company_id' => $otherCompany->id,
        ]);

        $this->withToken(JWTAuth::fromUser($this->admin))
            ->postJson('/api/v1/daily-reports/creditors', [
                'store_id' => $otherStore->id,
                'name' => 'Bukan Tenant Saya',
                'opening_balance' => 0,
                'opening_date' => '2026-08-23',
            ])->assertUnprocessable();
    }

    public function test_historical_restock_correction_recalculates_later_debt(): void
    {
        $creditor = DailyReportCreditor::create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'name' => 'Iqbal',
            'opening_balance' => 100000,
            'opening_date' => '2026-08-22',
        ]);
        $category = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Belanja Bahan',
            'code' => 'RESTOCK',
        ]);
        $token = JWTAuth::fromUser($this->admin);
        $url = '/api/v1/daily-reports/2026-08-22/restock';

        foreach ([225000, 100000] as $amount) {
            $this->withToken($token)->putJson($url, [
                'store_id' => $this->store->id,
                'creditor_id' => $creditor->id,
                'items' => [[
                    'name' => 'Belanja',
                    'expense_category_id' => $category->id,
                    'amount' => $amount,
                ]],
            ])->assertOk();
        }

        $this->withToken($token)
            ->getJson('/api/v1/daily-reports/2026-08-23?store_id='.$this->store->id)
            ->assertOk()
            ->assertJsonPath('data.debt.0.opening_balance', 200000)
            ->assertJsonPath('data.debt.0.closing_balance', 200000);
    }

    private function creditor(): DailyReportCreditor
    {
        return DailyReportCreditor::create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'name' => 'Iqbal',
            'opening_balance' => 100000,
            'opening_date' => '2026-08-23',
        ]);
    }

    private function transactionItem(
        Product $product,
        Store $store,
        string $type,
        string $paymentType,
        int $amount,
        string $date = '2026-08-23',
    ): void {
        $transaction = Transaction::create([
            'company_id' => $this->company->id,
            'code' => uniqid('TRX'),
            'date' => $date,
            'total' => $amount,
            'sub_total' => $amount,
            'total_item' => 1,
            'type' => $type,
            'payment_type' => $paymentType,
            'status' => 'PAID',
        ]);

        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'store_id' => $store->id,
            'name' => $product->name,
            'code' => $product->code,
            'price' => $amount,
            'qty' => 1,
            'total_price' => $amount,
        ]);
    }
}
