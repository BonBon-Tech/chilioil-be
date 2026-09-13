<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Transaction;
use App\Repository\TransactionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_transaction_numbers_are_not_reused_for_paid_or_pending_sales(): void
    {
        $company = Company::create(['name' => 'Test', 'slug' => 'trx-code-test', 'plan' => 'pro']);
        $data = ['company_id' => $company->id, 'date' => '2026-09-13',
            'type' => 'OFFLINE', 'payment_type' => 'CASH', 'status' => 'PAID',
            'total' => 20000, 'sub_total' => 20000, 'total_item' => 1];
        $old = Transaction::create(['code' => 'TRX20260913001'] + $data);
        $old->delete();
        $generator = new \ReflectionMethod(TransactionRepository::class, 'generateTransactionCode');
        foreach (['PAID', 'PENDING'] as $index => $status) {
            $code = $generator->invoke(new TransactionRepository, '2026-09-13');
            $this->assertSame('TRX2026091300'.($index + 2), $code);
            $new = Transaction::create(['code' => $code, 'status' => $status] + $data);
            $this->assertSame($status, $new->status);
            $new->delete();
        }
        $this->assertSoftDeleted('transactions', ['id' => $old->id]);
    }
}
