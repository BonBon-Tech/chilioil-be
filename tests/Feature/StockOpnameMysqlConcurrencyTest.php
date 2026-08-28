<?php

namespace Tests\Feature;

use App\Helpers\JwtClaims;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\StockOpname;
use App\Models\Store;
use App\Models\User;
use App\Repository\StockOpnameRepository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

#[Group('mysql-concurrency')]
class StockOpnameMysqlConcurrencyTest extends TestCase
{
    private Company $company;
    private Store $store;
    private ProductCategory $category;
    private Product $product;
    private User $admin;
    private string $token;
    private string $syncDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        if (env('RUN_MYSQL_CONCURRENCY_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_MYSQL_CONCURRENCY_TESTS=1 with a dedicated MySQL test database.');
        }
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL is required for row-lock concurrency coverage.');
        }
        if (!app()->environment('testing')) {
            $this->fail('Refusing destructive setup unless APP_ENV is exactly testing.');
        }

        $expectedDatabase = (string) env('MYSQL_CONCURRENCY_TEST_DATABASE');
        if ($expectedDatabase === '' || (string) DB::getDatabaseName() !== $expectedDatabase) {
            $this->fail('MYSQL_CONCURRENCY_TEST_DATABASE must exactly match the configured dedicated database.');
        }

        $host = strtolower((string) DB::connection()->getConfig('host'));
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $this->fail('Refusing destructive setup unless the MySQL host is local loopback.');
        }
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('The dedicated MySQL test database is unavailable.');
        }
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required for overlapping workers.');
        }

        $this->artisan('migrate:fresh')->assertExitCode(0);

        $this->company = Company::create([
            'name' => 'Concurrency Test',
            'slug' => 'concurrency-test',
            'plan' => 'pro',
        ]);
        $this->store = Store::create([
            'name' => 'Store A',
            'company_id' => $this->company->id,
        ]);
        $this->category = ProductCategory::create([
            'name' => 'Ingredients',
            'slug' => 'concurrency-ingredients',
            'company_id' => $this->company->id,
        ]);
        $this->product = $this->createProduct($this->store, 'Product A');
        $this->admin = User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => 'admin'])->id,
            'company_id' => $this->company->id,
        ]);
        $this->token = JWTAuth::fromUser($this->admin);
        $this->syncDirectory = sys_get_temp_dir().'/stock-opname-race-'.bin2hex(random_bytes(8));
        mkdir($this->syncDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->syncDirectory) && is_dir($this->syncDirectory)) {
            foreach (glob($this->syncDirectory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->syncDirectory);
        }

        parent::tearDown();
    }

    public function test_simultaneous_pending_transitions_have_one_winner(): void
    {
        $opname = $this->createStockOpname('SO-RACE-TRANSITION', $this->store, 1);
        $probe = 'transition';

        $results = $this->runConcurrently([
            function () use ($opname, $probe) {
                $this->holdFirstStockOpnameRead($probe, $opname->id);

                return app(StockOpnameRepository::class)->reject($opname->id)?->status;
            },
            function () use ($opname, $probe) {
                $this->waitUntil(fn() => file_exists($this->probePath($probe, 'first-read')));
                $this->observeSecondStockOpnameRead($probe, $opname->id);

                return app(StockOpnameRepository::class)->cancel($opname->id)?->status;
            },
        ]);

        $this->assertCount(1, array_filter($results, fn($result) => $result !== null));
        $this->assertContains(StockOpname::findOrFail($opname->id)->status, ['rejected', 'cancelled']);
    }

    public function test_item_update_waiting_on_transition_fails_after_status_changes(): void
    {
        $opname = $this->createStockOpname('SO-RACE-ITEM', $this->store, 1);
        $item = $opname->items()->create([
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'last_known_stock' => 0,
        ]);
        $transitionUpdated = $this->syncDirectory.'/transition-updated';

        $results = $this->runConcurrently([
            function () use ($opname, $transitionUpdated) {
                DB::listen(function (QueryExecuted $query) use ($transitionUpdated) {
                    if (str_contains(strtolower($query->sql), 'update `stock_opnames`') &&
                        in_array('rejected', $query->bindings, true)) {
                        touch($transitionUpdated);
                        usleep(500000);
                    }
                });

                return app(StockOpnameRepository::class)->reject($opname->id)?->status;
            },
            function () use ($opname, $item, $transitionUpdated) {
                $this->waitUntil(fn() => file_exists($transitionUpdated));

                return app(StockOpnameRepository::class)->updateItem(
                    $opname->id,
                    $item->id,
                    ['counted_stock' => 9],
                    $this->admin->id,
                )?->counted_stock;
            },
        ]);

        $this->assertSame('rejected', $results[0]);
        $this->assertNull($results[1]);
        $this->assertSame('rejected', StockOpname::findOrFail($opname->id)->status);
        $this->assertNull($item->fresh()->counted_stock);
    }

    public function test_simultaneous_delete_only_soft_deletes_once(): void
    {
        $opname = $this->createStockOpname('SO-RACE-DELETE', $this->store, 1, 'cancelled');
        $probe = 'delete';

        $results = $this->runConcurrently([
            function () use ($opname, $probe) {
                $this->holdFirstStockOpnameRead($probe, $opname->id);

                return app(StockOpnameRepository::class)->delete($opname->id);
            },
            function () use ($opname, $probe) {
                $this->waitUntil(fn() => file_exists($this->probePath($probe, 'first-read')));
                $this->observeSecondStockOpnameRead($probe, $opname->id);

                return app(StockOpnameRepository::class)->delete($opname->id);
            },
        ]);

        $this->assertSame(1, count(array_filter($results)));
        $this->assertNotNull(StockOpname::withTrashed()->findOrFail($opname->id)->deleted_at);
    }

    public function test_simultaneous_creation_allocates_unique_sequences(): void
    {
        $secondStore = Store::create([
            'name' => 'Store B',
            'company_id' => $this->company->id,
        ]);
        $this->createProduct($secondStore, 'Product B');
        $probe = 'sequence';

        $results = $this->runConcurrently([
            function () use ($probe) {
                $this->holdFirstSequenceRead($probe);

                return app(StockOpnameRepository::class)->create([
                    'opname_date' => '2026-08-27',
                    'store_id' => $this->store->id,
                    'started_by' => $this->admin->id,
                ])->only(['code', 'sequence_number']);
            },
            function () use ($secondStore, $probe) {
                $this->waitUntil(fn() => file_exists($this->probePath($probe, 'first-read')));
                $this->observeSecondSequenceRead($probe);

                return app(StockOpnameRepository::class)->create([
                    'opname_date' => '2026-08-27',
                    'store_id' => $secondStore->id,
                    'started_by' => $this->admin->id,
                ])->only(['code', 'sequence_number']);
            },
        ]);

        $this->assertSame([1, 2], collect($results)->pluck('sequence_number')->sort()->values()->all());
        $this->assertCount(2, collect($results)->pluck('code')->unique());
        $this->assertDatabaseCount('stock_opnames', 2);
    }

    private function holdFirstStockOpnameRead(string $probe, string $opnameId): void
    {
        $handled = false;
        DB::listen(function (QueryExecuted $query) use ($probe, $opnameId, &$handled) {
            if ($handled || !$this->isStockOpnameRead($query->sql, $query->bindings, $opnameId)) {
                return;
            }

            $handled = true;
            touch($this->probePath($probe, 'first-read'));
            $this->waitUntil(fn() => file_exists($this->probePath($probe, 'second-attempt')));
            $this->waitForSecondReadOrLock($probe);
            touch($this->probePath($probe, 'release'));
        });
    }

    private function observeSecondStockOpnameRead(string $probe, string $opnameId): void
    {
        $attempted = false;
        DB::connection()->beforeExecuting(function (string $query, array $bindings) use ($probe, $opnameId, &$attempted) {
            if (!$attempted && $this->isStockOpnameRead($query, $bindings, $opnameId)) {
                $attempted = true;
                touch($this->probePath($probe, 'second-attempt'));
            }
        });

        $observed = false;
        DB::listen(function (QueryExecuted $query) use ($probe, $opnameId, &$observed) {
            if ($observed || !$this->isStockOpnameRead($query->sql, $query->bindings, $opnameId)) {
                return;
            }

            $observed = true;
            touch($this->probePath($probe, 'second-read'));
            $this->waitUntil(fn() => file_exists($this->probePath($probe, 'release')));
        });
    }

    private function holdFirstSequenceRead(string $probe): void
    {
        $handled = false;
        DB::listen(function (QueryExecuted $query) use ($probe, &$handled) {
            if ($handled || !$this->isSequenceAllocationRead($query->sql)) {
                return;
            }

            $handled = true;
            touch($this->probePath($probe, 'first-read'));
            $this->waitUntil(fn() => file_exists($this->probePath($probe, 'second-attempt')));
            $this->waitForSecondReadOrLock($probe);
            touch($this->probePath($probe, 'release'));
        });
    }

    private function observeSecondSequenceRead(string $probe): void
    {
        $attempted = false;
        DB::connection()->beforeExecuting(function (string $query, array $bindings) use ($probe, &$attempted) {
            if (!$attempted && (
                $this->isSequenceAllocationRead($query) ||
                $this->isCompanyLockQuery($query, $bindings)
            )) {
                $attempted = true;
                touch($this->probePath($probe, 'second-attempt'));
            }
        });

        $observed = false;
        DB::listen(function (QueryExecuted $query) use ($probe, &$observed) {
            if ($observed || !$this->isSequenceAllocationRead($query->sql)) {
                return;
            }

            $observed = true;
            touch($this->probePath($probe, 'second-read'));
            $this->waitUntil(fn() => file_exists($this->probePath($probe, 'release')));
        });
    }

    private function waitForSecondReadOrLock(string $probe): void
    {
        $connectionId = (int) file_get_contents($this->syncDirectory.'/connection-1');
        $this->waitUntil(fn() =>
            file_exists($this->probePath($probe, 'second-read')) ||
            $this->isConnectionWaitingForLock($connectionId)
        );
    }

    private function isConnectionWaitingForLock(int $connectionId): bool
    {
        $transaction = DB::selectOne(
            'SELECT trx_state FROM information_schema.innodb_trx WHERE trx_mysql_thread_id = ?',
            [$connectionId],
        );

        return strtoupper((string) ($transaction?->trx_state ?? '')) === 'LOCK WAIT';
    }

    private function isStockOpnameRead(string $query, array $bindings, string $opnameId): bool
    {
        $query = strtolower(ltrim($query));

        return str_starts_with($query, 'select') &&
            str_contains($query, 'from `stock_opnames`') &&
            in_array($opnameId, $bindings, true);
    }

    private function isSequenceAllocationRead(string $query): bool
    {
        $query = strtolower($query);

        return str_contains($query, 'from `stock_opnames`') && (
            str_contains($query, 'max(') || str_contains($query, 'count(*) as aggregate')
        );
    }

    private function isCompanyLockQuery(string $query, array $bindings): bool
    {
        $query = strtolower($query);

        return str_contains($query, 'from `companies`') &&
            str_contains($query, 'for update') &&
            in_array($this->company->id, $bindings, true);
    }

    private function probePath(string $probe, string $event): string
    {
        return $this->syncDirectory.'/'.$probe.'-'.$event;
    }

    private function runConcurrently(array $operations): array
    {
        DB::disconnect();
        $children = [];

        foreach ($operations as $index => $operation) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('Unable to fork concurrency worker.');
            }
            if ($pid === 0) {
                DB::purge();
                DB::reconnect();
                JwtClaims::flush();

                try {
                    request()->headers->set('Authorization', 'Bearer '.$this->token);
                    JWTAuth::parseToken()->authenticate();
                    $connectionId = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
                    file_put_contents($this->syncDirectory.'/connection-'.$index, (string) $connectionId);
                    touch($this->syncDirectory.'/ready-'.$index);
                    $this->waitUntil(fn() => file_exists($this->syncDirectory.'/go'));
                    $payload = ['value' => $operation()];
                } catch (\Throwable $throwable) {
                    $payload = ['error' => $throwable::class.': '.$throwable->getMessage()];
                }

                file_put_contents($this->syncDirectory.'/result-'.$index, json_encode($payload, JSON_THROW_ON_ERROR));
                exit(isset($payload['error']) ? 1 : 0);
            }

            $children[$index] = $pid;
        }

        $this->waitUntil(function () use ($children) {
            foreach (array_keys($children) as $index) {
                if (!file_exists($this->syncDirectory.'/ready-'.$index)) {
                    return false;
                }
            }

            return true;
        });
        touch($this->syncDirectory.'/go');

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::purge();
        DB::reconnect();

        $results = [];
        foreach (array_keys($children) as $index) {
            $payload = json_decode(file_get_contents($this->syncDirectory.'/result-'.$index), true, flags: JSON_THROW_ON_ERROR);
            if (isset($payload['error'])) {
                $this->fail($payload['error']);
            }
            $results[$index] = $payload['value'];
        }

        return $results;
    }

    private function waitUntil(callable $condition): void
    {
        $deadline = microtime(true) + 10;
        while (!$condition()) {
            if (microtime(true) >= $deadline) {
                throw new \RuntimeException('Timed out waiting for concurrent worker synchronization.');
            }
            usleep(10000);
        }
    }

    private function createStockOpname(
        string $code,
        Store $store,
        int $sequence,
        string $status = 'pending',
    ): StockOpname {
        return StockOpname::create([
            'code' => $code,
            'company_id' => $this->company->id,
            'store_id' => $store->id,
            'started_by' => $this->admin->id,
            'opname_date' => '2026-08-27',
            'sequence_number' => $sequence,
            'status' => $status,
        ]);
    }

    private function createProduct(Store $store, string $name): Product
    {
        return Product::create([
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '-', $name)).'-'.$store->id,
            'store_id' => $store->id,
            'product_category_id' => $this->category->id,
            'selling_type' => 'Purchase',
            'price' => 1000,
        ]);
    }
}
