<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashChannelMapping;
use App\Models\Company;
use App\Models\DailyReport;
use App\Models\DailyReportCreditor;
use App\Models\DailyReportRestockItem;
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

        $creditor = $this->creditor();
        $this->withToken($token)
            ->putJson('/api/v1/daily-reports/2026-08-23/debt', [
                'store_id' => $this->store->id,
                'payments' => [[
                    'creditor_id' => $creditor->id,
                    'amount' => 20000,
                ]],
            ])->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/daily-reports/summary?store_id='.$this->store->id)
            ->assertOk()
            ->assertJsonPath('data.income', 130000)
            ->assertJsonPath('data.expense', 30000)
            ->assertJsonPath('data.balance', 100000)
            ->assertJsonPath('data.debt', 80000)
            ->assertJsonPath('data.debt_paid', 20000);
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

    public function test_reported_income_updates_default_cash_account_balances(): void
    {
        $token = JWTAuth::fromUser($this->admin);

        $setup = $this->withToken($token)
            ->postJson('/api/v1/daily-reports/accounts/setup', [
                'store_id' => $this->store->id,
                'opening_date' => '2026-08-23',
                'balances' => [
                    'bca' => 100000,
                    'mandiri' => 200000,
                    'cash' => 50000,
                ],
            ])
            ->assertOk()
            ->json('data');

        $this->withToken($token)
            ->putJson('/api/v1/daily-reports/2026-08-23/income', [
                'store_id' => $this->store->id,
                'amounts' => [
                    'shopeefood' => 244000,
                    'grabfood' => 30000,
                    'gofood' => 10000,
                    'qris' => 12000,
                    'cash' => 22000,
                ],
            ])
            ->assertOk();

        $accounts = $this->withToken($token)
            ->getJson('/api/v1/daily-reports/accounts?store_id='.$this->store->id)
            ->assertOk()
            ->json('data.accounts');

        $balances = collect($accounts)->pluck('balance', 'code');
        $this->assertEquals(130000, $balances['bca']);
        $this->assertEquals(466000, $balances['mandiri']);
        $this->assertEquals(72000, $balances['cash']);
        $this->assertSame($setup['mappings']['grabfood'], collect($accounts)->firstWhere('code', 'bca')['id']);
    }

    public function test_paid_expense_can_split_accounts_and_debt_expense_does_not_reduce_cash(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $accounts = $this->setupCashAccounts($token, bca: 500000, mandiri: 0, cash: 100000);
        $creditor = $this->creditor();
        $category = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Operasional',
            'code' => 'OPS-LEDGER',
        ]);

        $this->withToken($token)
            ->putJson('/api/v1/daily-reports/2026-08-23/expenses', [
                'store_id' => $this->store->id,
                'items' => [
                    [
                        'name' => 'Gaji Karyawan',
                        'expense_category_id' => $category->id,
                        'amount' => 450000,
                        'allocations' => [
                            ['account_id' => $accounts['cash'], 'amount' => 50000],
                            ['account_id' => $accounts['bca'], 'amount' => 400000],
                        ],
                    ],
                    [
                        'name' => 'Belanja Ayam',
                        'expense_category_id' => $category->id,
                        'amount' => 225000,
                        'creditor_id' => $creditor->id,
                        'allocations' => [],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.expenses.total', 675000)
            ->assertJsonCount(2, 'data.expenses.items')
            ->assertJsonPath('data.debt.0.closing_balance', 325000);

        $balances = $this->accountBalances($token);
        $this->assertEquals(100000, $balances['bca']);
        $this->assertEquals(50000, $balances['cash']);
    }

    public function test_debt_payment_reduces_selected_account_and_transfer_preserves_total(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $accounts = $this->setupCashAccounts($token, bca: 500000, mandiri: 300000, cash: 0);
        $creditor = $this->creditor();

        $this->withToken($token)
            ->putJson('/api/v1/daily-reports/2026-08-23/debt', [
                'store_id' => $this->store->id,
                'payments' => [[
                    'creditor_id' => $creditor->id,
                    'account_id' => $accounts['bca'],
                    'amount' => 25000,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.debt.0.closing_balance', 75000)
            ->assertJsonPath('data.debt.0.account_id', $accounts['bca']);

        $this->withToken($token)
            ->postJson('/api/v1/daily-reports/balance/transfers', [
                'store_id' => $this->store->id,
                'date' => '2026-08-23',
                'source_account_id' => $accounts['mandiri'],
                'destination_account_id' => $accounts['bca'],
                'amount' => 100000,
                'note' => 'Dana pembayaran utang',
            ])
            ->assertCreated();

        $balances = $this->accountBalances($token);
        $this->assertEquals(575000, $balances['bca']);
        $this->assertEquals(200000, $balances['mandiri']);
        $this->assertEquals(775000, array_sum($balances));
    }

    public function test_transfer_admin_fee_only_reduces_source_and_counts_as_expense(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $accounts = $this->setupCashAccounts($token, bca: 0, mandiri: 300000, cash: 0);

        $this->withToken($token)
            ->postJson('/api/v1/daily-reports/balance/transfers', [
                'store_id' => $this->store->id,
                'date' => '2026-08-23',
                'source_account_id' => $accounts['mandiri'],
                'destination_account_id' => $accounts['bca'],
                'amount' => 100000,
                'admin_fee' => 2500,
            ])
            ->assertCreated();

        $balances = $this->accountBalances($token);
        $this->assertEquals(100000, $balances['bca']);
        $this->assertEquals(197500, $balances['mandiri']);
        $this->assertDatabaseHas('cash_ledger_entries', [
            'type' => 'transfer_fee',
            'source_account_id' => $accounts['mandiri'],
            'destination_account_id' => null,
            'amount' => 2500,
        ]);
        $this->withToken($token)
            ->getJson('/api/v1/daily-reports/summary?store_id='.$this->store->id)
            ->assertOk()
            ->assertJsonPath('data.expense', 2500)
            ->assertJsonPath('data.cash_total', 297500);
    }

    public function test_expense_batches_append_without_replacing_existing_items(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $creditor = $this->creditor();
        $category = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Restok Bahan',
            'code' => 'RESTOCK-BATCH',
        ]);
        $item = fn (string $name, int $amount) => [
            'name' => $name,
            'expense_category_id' => $category->id,
            'amount' => $amount,
            'creditor_id' => $creditor->id,
            'allocations' => [],
        ];

        $this->withToken($token)->postJson('/api/v1/daily-reports/2026-08-23/expenses', [
            'store_id' => $this->store->id,
            'items' => [$item('Ayam', 210000), $item('Koya', 15000)],
        ])->assertCreated()->assertJsonCount(2, 'data.expenses.items');

        $this->withToken($token)->postJson('/api/v1/daily-reports/2026-08-23/expenses', [
            'store_id' => $this->store->id,
            'items' => [$item('Kecap', 30000)],
        ])->assertCreated()
            ->assertJsonCount(3, 'data.expenses.items')
            ->assertJsonPath('data.expenses.items.0.expense_category_name', 'Restok Bahan');

        $this->assertDatabaseHas('daily_report_restock_items', ['name' => 'Ayam', 'amount' => 210000]);
        $this->assertDatabaseHas('daily_report_restock_items', ['name' => 'Kecap', 'amount' => 30000]);
    }

    public function test_paid_expense_batch_can_reuse_the_same_account_across_items(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $accounts = $this->setupCashAccounts($token, bca: 300000, mandiri: 0, cash: 0);
        $category = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Restok Bahan',
            'code' => 'RESTOCK-PAID-BATCH',
        ]);
        $item = fn (string $name, int $amount) => [
            'name' => $name,
            'expense_category_id' => $category->id,
            'amount' => $amount,
            'allocations' => [[
                'account_id' => $accounts['bca'],
                'amount' => $amount,
            ]],
        ];

        $this->withToken($token)->postJson('/api/v1/daily-reports/2026-08-23/expenses', [
            'store_id' => $this->store->id,
            'items' => [$item('Ayam', 100000), $item('Koya', 25000)],
        ])->assertCreated()->assertJsonCount(2, 'data.expenses.items');

        $this->assertEquals(175000, $this->accountBalances($token)['bca']);

        $this->withToken($token)->postJson('/api/v1/daily-reports/2026-08-23/expenses', [
            'store_id' => $this->store->id,
            'items' => [[
                'name' => 'Gas',
                'expense_category_id' => $category->id,
                'amount' => 50000,
                'allocations' => [
                    ['account_id' => $accounts['bca'], 'amount' => 25000],
                    ['account_id' => $accounts['bca'], 'amount' => 25000],
                ],
            ]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.allocations');
    }

    public function test_updating_one_daily_expense_preserves_its_siblings(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $creditor = $this->creditor();
        $category = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Restok Bahan',
            'code' => 'RESTOCK-EDIT',
        ]);
        $item = fn (string $name, int $amount) => [
            'name' => $name,
            'expense_category_id' => $category->id,
            'amount' => $amount,
            'creditor_id' => $creditor->id,
            'allocations' => [],
        ];
        $items = $this->withToken($token)->postJson('/api/v1/daily-reports/2026-08-23/expenses', [
            'store_id' => $this->store->id,
            'items' => [$item('Ayam', 210000), $item('Koya', 15000)],
        ])->json('data.expenses.items');

        $this->withToken($token)
            ->putJson('/api/v1/daily-reports/2026-08-23/expenses/'.$items[0]['id'], [
                'store_id' => $this->store->id,
                'items' => [$item('Ayam fillet', 225000)],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.expenses.items');

        $this->assertDatabaseHas('daily_report_restock_items', ['id' => $items[0]['id'], 'name' => 'Ayam fillet', 'amount' => 225000]);
        $this->assertDatabaseHas('daily_report_restock_items', ['id' => $items[1]['id'], 'name' => 'Koya', 'amount' => 15000]);
    }

    public function test_deleting_one_daily_expense_restores_its_balance_and_preserves_siblings(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $accounts = $this->setupCashAccounts($token, bca: 300000, mandiri: 0, cash: 0);
        $category = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Restok Bahan',
            'code' => 'RESTOCK-DELETE',
        ]);
        $item = fn (string $name, int $amount) => [
            'name' => $name,
            'expense_category_id' => $category->id,
            'amount' => $amount,
            'allocations' => [['account_id' => $accounts['bca'], 'amount' => $amount]],
        ];
        $items = $this->withToken($token)->postJson('/api/v1/daily-reports/2026-08-23/expenses', [
            'store_id' => $this->store->id,
            'items' => [$item('Ayam', 100000), $item('Koya', 25000)],
        ])->assertCreated()->json('data.expenses.items');

        $expenseId = DailyReportRestockItem::findOrFail($items[0]['id'])->expense_id;
        $this->withToken($token)->deleteJson('/api/v1/daily-reports/2026-08-23/expenses/'.$items[0]['id'], [
            'store_id' => $this->store->id,
        ])->assertOk()->assertJsonCount(1, 'data.expenses.items');

        $this->assertEquals(275000, $this->accountBalances($token)['bca']);
        $this->assertSoftDeleted('daily_report_restock_items', ['id' => $items[0]['id']]);
        $this->assertSoftDeleted('expenses', ['id' => $expenseId]);
        $this->assertDatabaseHas('daily_report_restock_items', ['id' => $items[1]['id'], 'name' => 'Koya']);
    }

    public function test_adjustment_requires_note_and_outgoing_cannot_make_balance_negative(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $accounts = $this->setupCashAccounts($token, bca: 100000, mandiri: 0, cash: 0);

        $this->withToken($token)
            ->postJson('/api/v1/daily-reports/balance/adjustments', [
                'store_id' => $this->store->id,
                'date' => '2026-08-23',
                'account_id' => $accounts['bca'],
                'actual_balance' => 90000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');

        $this->withToken($token)
            ->postJson('/api/v1/daily-reports/balance/adjustments', [
                'store_id' => $this->store->id,
                'date' => '2026-08-23',
                'account_id' => $accounts['bca'],
                'actual_balance' => 90000,
                'note' => 'Selisih mutasi bank',
            ])
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/daily-reports/balance/transfers', [
                'store_id' => $this->store->id,
                'date' => '2026-08-23',
                'source_account_id' => $accounts['bca'],
                'destination_account_id' => $accounts['mandiri'],
                'amount' => 90001,
            ])
            ->assertUnprocessable();

        $this->assertEquals(90000, $this->accountBalances($token)['bca']);
    }

    public function test_admin_can_add_account_and_mapping_change_is_not_retroactive(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $accounts = $this->setupCashAccounts($token, bca: 100000, mandiri: 100000, cash: 0);

        $wallet = $this->withToken($token)
            ->postJson('/api/v1/daily-reports/accounts', [
                'store_id' => $this->store->id,
                'name' => 'SeaBank',
                'opening_date' => '2026-08-23',
                'opening_balance' => 50000,
            ])
            ->assertCreated()
            ->json('data');

        $this->withToken($token)
            ->putJson('/api/v1/daily-reports/channel-mappings', [
                'store_id' => $this->store->id,
                'mappings' => [
                    'shopeefood' => $accounts['mandiri'],
                    'grabfood' => $wallet['id'],
                    'gofood' => $accounts['mandiri'],
                    'qris' => $accounts['mandiri'],
                    'cash' => $accounts['cash'],
                ],
            ])->assertOk();

        $income = fn (int $grab) => [
            'store_id' => $this->store->id,
            'amounts' => ['shopeefood' => 0, 'grabfood' => $grab, 'gofood' => 0, 'qris' => 0, 'cash' => 0],
        ];
        $this->withToken($token)->putJson('/api/v1/daily-reports/2026-08-23/income', $income(20000))->assertOk();

        $this->withToken($token)
            ->putJson('/api/v1/daily-reports/channel-mappings', [
                'store_id' => $this->store->id,
                'mappings' => [
                    'shopeefood' => $accounts['mandiri'],
                    'grabfood' => $accounts['bca'],
                    'gofood' => $accounts['mandiri'],
                    'qris' => $accounts['mandiri'],
                    'cash' => $accounts['cash'],
                ],
            ])->assertOk();
        $this->withToken($token)->putJson('/api/v1/daily-reports/2026-08-23/income', $income(30000))->assertOk();

        $byCode = $this->accountBalances($token);
        $this->assertEquals(80000, $byCode['seabank']);
        $this->assertEquals(100000, $byCode['bca']);
    }

    public function test_zero_income_still_freezes_the_mapping_for_that_report(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $accounts = $this->setupCashAccounts($token, bca: 0, mandiri: 0, cash: 0);
        $payload = [
            'store_id' => $this->store->id,
            'amounts' => ['shopeefood' => 0, 'grabfood' => 0, 'gofood' => 0, 'qris' => 0, 'cash' => 0],
        ];

        $this->withToken($token)->putJson('/api/v1/daily-reports/2026-08-23/income', $payload)->assertOk();
        $this->withToken($token)->putJson('/api/v1/daily-reports/channel-mappings', [
            'store_id' => $this->store->id,
            'mappings' => [
                'shopeefood' => $accounts['cash'],
                'grabfood' => $accounts['cash'],
                'gofood' => $accounts['cash'],
                'qris' => $accounts['cash'],
                'cash' => $accounts['cash'],
            ],
        ])->assertOk();
        $payload['amounts']['grabfood'] = 100000;
        $this->withToken($token)->putJson('/api/v1/daily-reports/2026-08-23/income', $payload)->assertOk();

        $balances = $this->accountBalances($token);
        $this->assertEquals(100000, $balances['bca']);
        $this->assertEquals(0, $balances['cash']);
    }

    public function test_historical_adjustment_is_rejected_when_it_makes_a_later_balance_negative(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $accounts = $this->withToken($token)
            ->postJson('/api/v1/daily-reports/accounts/setup', [
                'store_id' => $this->store->id,
                'opening_date' => '2026-08-22',
                'balances' => ['bca' => 100000, 'mandiri' => 0, 'cash' => 0],
            ])->assertOk()->json('data.accounts');
        $ids = collect($accounts)->pluck('id', 'code');

        $this->withToken($token)->postJson('/api/v1/daily-reports/balance/transfers', [
            'store_id' => $this->store->id,
            'date' => '2026-08-23',
            'source_account_id' => $ids['bca'],
            'destination_account_id' => $ids['mandiri'],
            'amount' => 100000,
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/daily-reports/balance/adjustments', [
            'store_id' => $this->store->id,
            'date' => '2026-08-22',
            'account_id' => $ids['bca'],
            'actual_balance' => 50000,
            'note' => 'Koreksi saldo lama',
        ])->assertUnprocessable();

        $this->assertEquals(0, $this->accountBalances($token)['bca']);
        $this->assertDatabaseMissing('cash_ledger_entries', ['note' => 'Koreksi saldo lama']);
    }

    public function test_balance_history_contains_daily_account_totals_and_entries(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $accounts = $this->setupCashAccounts($token, bca: 100000, mandiri: 100000, cash: 0);
        $this->withToken($token)->postJson('/api/v1/daily-reports/balance/transfers', [
            'store_id' => $this->store->id,
            'date' => '2026-08-23',
            'source_account_id' => $accounts['mandiri'],
            'destination_account_id' => $accounts['bca'],
            'amount' => 25000,
            'note' => 'Pindah dana',
        ])->assertCreated();

        $response = $this->withToken($token)
            ->getJson('/api/v1/daily-reports/history?tab=balance&store_id='.$this->store->id.'&per_page=15&page=1')
            ->assertOk()
            ->assertJsonPath('data.data.0.report_date', '2026-08-23')
            ->assertJsonPath('data.data.0.balance.entries.2.type', 'transfer')
            ->assertJsonPath('data.data.0.balance.accounts.0.closing_balance', 125000);

        $response->assertSee('"source":{}', false)
            ->assertSee('"reported":{}', false);
    }

    public function test_expense_endpoint_uses_store_ledger_and_delete_restores_balance(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $expenseFeature = Feature::create([
            'slug' => 'expenses', 'name' => 'Pengeluaran', 'route' => '/expenses',
            'icon' => 'wallet', 'group' => 'keuangan', 'sort_order' => 10,
        ]);
        PlanFeature::create(['plan' => 'pro', 'feature_id' => $expenseFeature->id, 'is_active' => true]);
        $accounts = $this->setupCashAccounts($token, bca: 200000, mandiri: 0, cash: 0);
        $category = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Sewa',
            'code' => 'RENT-LEDGER',
        ]);

        $expense = $this->withToken($token)->postJson('/api/v1/expenses', [
            'store_id' => $this->store->id,
            'expense_category_id' => $category->id,
            'date' => '2026-08-23',
            'amount' => 50000,
            'description' => 'Kontrakan',
            'allocations' => [['account_id' => $accounts['bca'], 'amount' => 50000]],
        ])->assertCreated()->json('data');

        $this->assertEquals(150000, $this->accountBalances($token)['bca']);
        $this->withToken($token)->deleteJson('/api/v1/expenses/'.$expense['id'])->assertOk();
        $this->assertEquals(200000, $this->accountBalances($token)['bca']);
    }

    public function test_initialized_ledger_requires_account_for_debt_payment(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $this->setupCashAccounts($token, bca: 100000, mandiri: 0, cash: 0);
        $creditor = $this->creditor();

        $this->withToken($token)->putJson('/api/v1/daily-reports/2026-08-23/debt', [
            'store_id' => $this->store->id,
            'payments' => [['creditor_id' => $creditor->id, 'amount' => 25000]],
        ])->assertUnprocessable()->assertJsonValidationErrors('payments.0.account_id');
    }

    public function test_historical_income_correction_rolls_back_when_later_balance_would_be_negative(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $setup = $this->withToken($token)->postJson('/api/v1/daily-reports/accounts/setup', [
            'store_id' => $this->store->id,
            'opening_date' => '2026-08-22',
            'balances' => ['bca' => 0, 'mandiri' => 0, 'cash' => 0],
        ])->assertOk()->json('data.accounts');
        $ids = collect($setup)->pluck('id', 'code');
        $payload = fn (int $grab) => [
            'store_id' => $this->store->id,
            'amounts' => ['shopeefood' => 0, 'grabfood' => $grab, 'gofood' => 0, 'qris' => 0, 'cash' => 0],
        ];

        $this->withToken($token)->putJson('/api/v1/daily-reports/2026-08-22/income', $payload(100000))->assertOk();
        $this->withToken($token)->postJson('/api/v1/daily-reports/balance/transfers', [
            'store_id' => $this->store->id,
            'date' => '2026-08-23',
            'source_account_id' => $ids['bca'],
            'destination_account_id' => $ids['mandiri'],
            'amount' => 100000,
        ])->assertCreated();

        $this->withToken($token)->putJson('/api/v1/daily-reports/2026-08-22/income', $payload(50000))
            ->assertUnprocessable();

        $this->assertDatabaseHas('daily_reports', ['report_date' => '2026-08-22', 'grabfood_amount' => 100000]);
        $this->assertEquals(0, $this->accountBalances($token)['bca']);
    }

    public function test_summary_includes_current_cash_accounts_and_total(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $this->setupCashAccounts($token, bca: 100000, mandiri: 200000, cash: 50000);

        $this->withToken($token)->getJson('/api/v1/daily-reports/summary?store_id='.$this->store->id)
            ->assertOk()
            ->assertJsonPath('data.cash_total', 350000)
            ->assertJsonCount(3, 'data.accounts');
    }

    public function test_account_used_by_mapping_cannot_be_deactivated(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $accounts = $this->setupCashAccounts($token, bca: 0, mandiri: 0, cash: 0);

        $this->withToken($token)->putJson('/api/v1/daily-reports/accounts/'.$accounts['bca'], [
            'store_id' => $this->store->id,
            'name' => 'BCA Utama',
            'is_active' => false,
        ])->assertUnprocessable();
    }

    public function test_updating_expense_replaces_its_ledger_allocations(): void
    {
        $token = JWTAuth::fromUser($this->admin);
        $expenseFeature = Feature::create([
            'slug' => 'expenses', 'name' => 'Pengeluaran', 'route' => '/expenses',
            'icon' => 'wallet', 'group' => 'keuangan', 'sort_order' => 10,
        ]);
        PlanFeature::create(['plan' => 'pro', 'feature_id' => $expenseFeature->id, 'is_active' => true]);
        $accounts = $this->setupCashAccounts($token, bca: 200000, mandiri: 0, cash: 100000);
        $category = ExpenseCategory::create([
            'company_id' => $this->company->id, 'name' => 'Operasional', 'code' => 'UPDATE-LEDGER',
        ]);
        $expense = $this->withToken($token)->postJson('/api/v1/expenses', [
            'store_id' => $this->store->id,
            'expense_category_id' => $category->id,
            'date' => '2026-08-23',
            'amount' => 50000,
            'description' => 'Sampah',
            'allocations' => [['account_id' => $accounts['bca'], 'amount' => 50000]],
        ])->assertCreated()->json('data');

        $this->withToken($token)->putJson('/api/v1/expenses/'.$expense['id'], [
            'store_id' => $this->store->id,
            'expense_category_id' => $category->id,
            'date' => '2026-08-23',
            'amount' => 60000,
            'description' => 'Sampah dan kebersihan',
            'allocations' => [['account_id' => $accounts['cash'], 'amount' => 60000]],
        ])->assertOk();

        $balances = $this->accountBalances($token);
        $this->assertEquals(200000, $balances['bca']);
        $this->assertEquals(40000, $balances['cash']);
        $this->assertDatabaseHas('daily_report_restock_items', [
            'expense_id' => $expense['id'], 'name' => 'Sampah dan kebersihan', 'amount' => 60000,
        ]);
    }

    public function test_staff_expense_mutations_are_limited_to_today_and_assigned_store(): void
    {
        $expenseFeature = Feature::create([
            'slug' => 'expenses', 'name' => 'Pengeluaran', 'route' => '/expenses',
            'icon' => 'wallet', 'group' => 'keuangan', 'sort_order' => 10,
        ]);
        PlanFeature::create(['plan' => 'pro', 'feature_id' => $expenseFeature->id, 'is_active' => true]);
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
        $category = ExpenseCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Operasional',
            'code' => 'OPS',
        ]);
        $ownCreditor = $this->creditor();
        $otherCreditor = DailyReportCreditor::create([
            'company_id' => $this->company->id,
            'store_id' => $otherStore->id,
            'name' => 'Supplier Lain',
            'opening_balance' => 0,
            'opening_date' => '2026-08-23',
        ]);
        $token = JWTAuth::fromUser($staff);

        $this->withToken($token)->postJson('/api/v1/expenses', [
            'store_id' => $this->store->id,
            'expense_category_id' => $category->id,
            'date' => '2026-08-23',
            'amount' => 10000,
            'creditor_id' => $ownCreditor->id,
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/expenses', [
            'store_id' => $otherStore->id,
            'expense_category_id' => $category->id,
            'date' => '2026-08-23',
            'amount' => 10000,
            'creditor_id' => $otherCreditor->id,
        ])->assertUnprocessable();

        $this->withToken($token)->postJson('/api/v1/expenses', [
            'store_id' => $this->store->id,
            'expense_category_id' => $category->id,
            'date' => '2026-08-22',
            'amount' => 10000,
            'creditor_id' => $ownCreditor->id,
        ])->assertForbidden();

        $pastExpense = Expense::create([
            'company_id' => $this->company->id,
            'store_id' => $this->store->id,
            'expense_category_id' => $category->id,
            'date' => '2026-08-22',
            'amount' => 10000,
        ]);
        $this->withToken($token)
            ->deleteJson('/api/v1/expenses/'.$pastExpense->id)
            ->assertForbidden();
    }

    public function test_new_store_gets_default_cash_accounts_and_mappings(): void
    {
        $store = Store::create([
            'name' => 'Store Baru',
            'company_id' => $this->company->id,
        ]);

        $this->assertSame(3, CashAccount::where('store_id', $store->id)->count());
        $this->assertDatabaseHas('cash_accounts', [
            'store_id' => $store->id,
            'code' => 'bca',
            'opened_on' => null,
        ]);
        $this->assertSame(5, CashChannelMapping::where('store_id', $store->id)->count());
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

    private function setupCashAccounts(string $token, int $bca, int $mandiri, int $cash): array
    {
        $data = $this->withToken($token)
            ->postJson('/api/v1/daily-reports/accounts/setup', [
                'store_id' => $this->store->id,
                'opening_date' => '2026-08-23',
                'balances' => compact('bca', 'mandiri', 'cash'),
            ])
            ->assertOk()
            ->json('data.accounts');

        return collect($data)->pluck('id', 'code')->all();
    }

    private function accountBalances(string $token): array
    {
        $accounts = $this->withToken($token)
            ->getJson('/api/v1/daily-reports/accounts?store_id='.$this->store->id)
            ->assertOk()
            ->json('data.accounts');

        return collect($accounts)->pluck('balance', 'code')->all();
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
