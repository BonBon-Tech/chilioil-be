<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AlloDulDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AlloDulDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_idempotent_tenant_scoped_ninety_day_traffic(): void
    {
        Carbon::setTestNow('2026-08-28 12:00:00');

        $this->seed(AlloDulDemoSeeder::class);

        $company = Company::where('name', 'Allo Dul')->sole();
        $user = User::where('email', 'dul@example.com')->sole();
        $transactions = Transaction::query()
            ->where('company_id', $company->id)
            ->where('code', 'like', 'DEMO-DUL-%')
            ->with(['store.company', 'transactionItems.product.store'])
            ->get();

        $this->assertSame($company->id, $user->company_id);
        $this->assertSame('admin', $user->role->name);
        $this->assertTrue(Hash::check('dul123456', $user->password));
        $this->assertGreaterThanOrEqual(900, $transactions->count());
        $this->assertSame('2026-05-31', $transactions->min('date')->toDateString());
        $this->assertSame('2026-08-28', $transactions->max('date')->toDateString());

        foreach ($transactions as $transaction) {
            $this->assertSame($company->id, $transaction->store->company_id);
            $this->assertNotEmpty($transaction->transactionItems);
            foreach ($transaction->transactionItems as $item) {
                $this->assertSame($transaction->store_id, $item->store_id);
                $this->assertSame($transaction->store_id, $item->product->store_id);
            }
        }

        $transactionCount = $transactions->count();
        $itemCount = $transactions->sum(fn (Transaction $transaction) => $transaction->transactionItems->count());

        $this->seed(AlloDulDemoSeeder::class);

        $this->assertSame(1, Company::where('name', 'Allo Dul')->count());
        $this->assertSame(1, User::where('email', 'dul@example.com')->count());
        $this->assertSame(
            $transactionCount,
            Transaction::where('company_id', $company->id)
                ->where('code', 'like', 'DEMO-DUL-%')
                ->count()
        );
        $this->assertSame(
            $itemCount,
            Transaction::where('company_id', $company->id)
                ->where('code', 'like', 'DEMO-DUL-%')
                ->withCount('transactionItems')
                ->get()
                ->sum('transaction_items_count')
        );
    }
}
