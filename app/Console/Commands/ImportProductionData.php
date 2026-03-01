<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ImportProductionData extends Command
{
    protected $signature = 'import:production-data {--force : Skip confirmation prompt}';
    protected $description = 'Convert and import old production dump (int IDs → UUID) into the current schema';

    private string $sql;
    private string $companyId;

    // Maps: old int ID → new UUID
    private array $roleMap     = [];
    private array $storeMap    = [];
    private array $userMap     = [];
    private array $catMap      = [];
    private array $productMap  = [];
    private array $expCatMap   = [];
    private array $transMap    = [];

    public function handle(): int
    {
        $path = storage_path('tmp/chilioilxsatenagihin-2026_03_01_11_53_15-dump.sql');

        if (!file_exists($path)) {
            $this->error("Dump file not found: $path");
            return 1;
        }

        $company = DB::table('companies')->where('slug', 'jajaneun-chili-oil')->first();
        if (!$company) {
            $this->error("Company 'jajaneun-chili-oil' not found. Run: php artisan migrate");
            return 1;
        }
        $this->companyId = $company->id;

        if (!$this->option('force') && !$this->confirm(
            "Import production data into company '{$company->name}'? This should only run once on a fresh database."
        )) {
            return 0;
        }

        $this->info('Reading dump file...');
        $this->sql = file_get_contents($path);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->mapRoles();
            $this->importStores();
            $this->importUsers();
            $this->importProductCategories();
            $this->importProducts();
            $this->importExpenseCategories();
            $this->importExpenses();
            $this->importTransactions();
            $this->importTransactionItems();
            $this->importOnlineTransactionDetails();
            $this->importCashFlows();
            $this->importWifiCredentials();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('');
        $this->info('✓ Production data import complete!');
        return 0;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Import methods
    // ──────────────────────────────────────────────────────────────────────────

    private function mapRoles(): void
    {
        $this->line('Mapping roles...');

        // Old dump roles: 1 = admin, 2 = staff
        // Create them if they don't exist yet (command may run before RoleSeeder)
        foreach (['admin' => 'Administrator', 'staff' => 'Staff member'] as $name => $desc) {
            $existing = DB::table('roles')->where('name', $name)->first();
            if ($existing) {
                $id = $existing->id;
            } else {
                $id = Str::uuid()->toString();
                DB::table('roles')->insert([
                    'id' => $id, 'name' => $name, 'description' => $desc,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ($name === 'admin') $this->roleMap[1] = $id;
            if ($name === 'staff') $this->roleMap[2] = $id;
        }

        $this->line('  ✓ Roles mapped (admin=' . ($this->roleMap[1] ?? '?') . ')');
    }

    private function importStores(): void
    {
        $this->line('Importing stores...');
        // Old columns: id, name, logo, created_at, updated_at, deleted_at
        $rows = $this->parseRows('stores');
        $data = [];
        foreach ($rows as $row) {
            $uuid = Str::uuid()->toString();
            $this->storeMap[$row[0]] = $uuid;
            $data[] = [
                'id'         => $uuid,
                'company_id' => $this->companyId,
                'name'       => $row[1],
                'logo'       => $row[2],
                'created_at' => $row[3],
                'updated_at' => $row[4],
                'deleted_at' => $row[5],
            ];
        }
        if (!empty($data)) DB::table('stores')->insertOrIgnore($data);
        $this->line('  ✓ ' . count($data) . ' stores');
    }

    private function importUsers(): void
    {
        $this->line('Importing users...');
        // Old columns: id, role_id, name, email, email_verified_at, password, remember_token, created_at, updated_at
        $rows = $this->parseRows('users');
        // Skip users that already exist (e.g. owner@example.com created by migration)
        $skipped = ['owner@example.com', 'demo@example.com'];
        $data = [];
        foreach ($rows as $row) {
            $email = $row[3];
            if (in_array($email, $skipped)) continue;
            if (DB::table('users')->where('email', $email)->exists()) continue;

            $uuid = Str::uuid()->toString();
            $this->userMap[$row[0]] = $uuid;
            $data[] = [
                'id'                => $uuid,
                'company_id'        => $this->companyId,
                'role_id'           => $this->roleMap[$row[1]] ?? null,
                'name'              => $row[2],
                'email'             => $email,
                'email_verified_at' => $row[4],
                'password'          => $row[5],
                'remember_token'    => $row[6],
                'created_at'        => $row[7],
                'updated_at'        => $row[8],
            ];
        }
        if (!empty($data)) DB::table('users')->insertOrIgnore($data);
        $this->line('  ✓ ' . count($data) . ' users');
    }

    private function importProductCategories(): void
    {
        $this->line('Importing product categories...');
        // Old columns: id, name, slug, logo, status, created_at, updated_at, deleted_at
        $rows = $this->parseRows('product_categories');
        $data = [];
        foreach ($rows as $row) {
            $uuid = Str::uuid()->toString();
            $this->catMap[$row[0]] = $uuid;
            $data[] = [
                'id'         => $uuid,
                'company_id' => $this->companyId,
                'name'       => $row[1],
                'slug'       => $row[2],
                'logo'       => $row[3],
                'status'     => $row[4],
                'created_at' => $row[5],
                'updated_at' => $row[6],
                'deleted_at' => $row[7],
            ];
        }
        if (!empty($data)) DB::table('product_categories')->insertOrIgnore($data);
        $this->line('  ✓ ' . count($data) . ' product categories');
    }

    private function importProducts(): void
    {
        $this->line('Importing products...');
        // Old columns: id, name, code, store_id, product_category_id, selling_type, image_path, price, status, created_at, updated_at, deleted_at
        $rows = $this->parseRows('products');
        $data = [];
        foreach ($rows as $row) {
            $uuid = Str::uuid()->toString();
            $this->productMap[$row[0]] = $uuid;
            $data[] = [
                'id'                  => $uuid,
                'name'                => $row[1],
                'code'                => $row[2],
                'store_id'            => $this->storeMap[$row[3]] ?? null,
                'product_category_id' => $this->catMap[$row[4]] ?? null,
                'selling_type'        => $row[5],
                'image_path'          => $row[6],
                'price'               => $row[7],
                'status'              => $row[8],
                'created_at'          => $row[9],
                'updated_at'          => $row[10],
                'deleted_at'          => $row[11],
            ];
        }
        if (!empty($data)) DB::table('products')->insertOrIgnore($data);
        $this->line('  ✓ ' . count($data) . ' products');
    }

    private function importExpenseCategories(): void
    {
        $this->line('Importing expense categories...');
        // Old columns: id, name, code, created_at, updated_at, deleted_at
        $rows = $this->parseRows('expense_categories');
        $data = [];
        foreach ($rows as $row) {
            $uuid = Str::uuid()->toString();
            $this->expCatMap[$row[0]] = $uuid;
            $data[] = [
                'id'         => $uuid,
                'company_id' => $this->companyId,
                'name'       => $row[1],
                'code'       => $row[2],
                'created_at' => $row[3],
                'updated_at' => $row[4],
                'deleted_at' => $row[5],
            ];
        }
        if (!empty($data)) DB::table('expense_categories')->insertOrIgnore($data);
        $this->line('  ✓ ' . count($data) . ' expense categories');
    }

    private function importExpenses(): void
    {
        $this->line('Importing expenses...');
        // Old columns: id, expense_category_id, date, amount, reference, description, created_at, updated_at, deleted_at
        $rows = $this->parseRows('expenses');
        $total = 0;
        foreach (array_chunk($rows, 100) as $chunk) {
            $data = [];
            foreach ($chunk as $row) {
                $data[] = [
                    'id'                  => Str::uuid()->toString(),
                    'company_id'          => $this->companyId,
                    'expense_category_id' => $this->expCatMap[$row[1]] ?? null,
                    'date'                => $row[2],
                    'amount'              => $row[3],
                    'reference'           => $row[4],
                    'description'         => $row[5],
                    'created_at'          => $row[6],
                    'updated_at'          => $row[7],
                    'deleted_at'          => $row[8],
                    'store_id'            => null,
                ];
            }
            DB::table('expenses')->insert($data);
            $total += count($data);
        }
        $this->line("  ✓ $total expenses");
    }

    private function importTransactions(): void
    {
        $this->line('Importing transactions...');
        // Old columns: id, code, date, customer_name, total, sub_total, total_item, type, payment_type, status, online_transaction_revenue, created_at, updated_at, deleted_at
        $rows = $this->parseRows('transactions');
        $total = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            $data = [];
            foreach ($chunk as $row) {
                $uuid = Str::uuid()->toString();
                $this->transMap[$row[0]] = $uuid;
                $data[] = [
                    'id'                         => $uuid,
                    'company_id'                 => $this->companyId,
                    'code'                       => $row[1],
                    'date'                       => $row[2],
                    'customer_name'              => $row[3],
                    'total'                      => $row[4],
                    'sub_total'                  => $row[5],
                    'total_item'                 => $row[6],
                    'type'                       => $row[7],
                    'payment_type'               => $row[8],
                    'status'                     => $row[9],
                    'online_transaction_revenue' => $row[10],
                    'created_at'                 => $row[11],
                    'updated_at'                 => $row[12],
                    'deleted_at'                 => $row[13],
                    'store_id'                   => null,
                ];
            }
            DB::table('transactions')->insert($data);
            $total += count($data);
        }
        $this->line("  ✓ $total transactions");
    }

    private function importTransactionItems(): void
    {
        $this->line('Importing transaction items...');
        // Old columns: id, transaction_id, product_id, image_path, store_id, name, code, price, qty, total_price, note, created_at, updated_at, deleted_at
        $rows = $this->parseRows('transaction_items');
        $total = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            $data = [];
            foreach ($chunk as $row) {
                $data[] = [
                    'id'             => Str::uuid()->toString(),
                    'transaction_id' => $this->transMap[$row[1]] ?? null,
                    'product_id'     => $this->productMap[$row[2]] ?? null,
                    'image_path'     => $row[3],
                    'store_id'       => $this->storeMap[$row[4]] ?? null,
                    'name'           => $row[5],
                    'code'           => $row[6],
                    'price'          => $row[7],
                    'qty'            => $row[8],
                    'total_price'    => $row[9],
                    'note'           => $row[10],
                    'created_at'     => $row[11],
                    'updated_at'     => $row[12],
                    'deleted_at'     => $row[13],
                ];
            }
            DB::table('transaction_items')->insert($data);
            $total += count($data);
        }
        $this->line("  ✓ $total transaction items");
    }

    private function importOnlineTransactionDetails(): void
    {
        $this->line('Importing online transaction details...');
        // Old columns: id, transaction_id, store_id, revenue, deleted_at, created_at, updated_at
        $rows = $this->parseRows('online_transaction_details');
        $total = 0;
        $skipped = 0;
        foreach (array_chunk($rows, 100) as $chunk) {
            $data = [];
            foreach ($chunk as $row) {
                $txUuid    = $this->transMap[$row[1]] ?? null;
                $storeUuid = $this->storeMap[$row[2]] ?? null;
                if (!$txUuid || !$storeUuid) { $skipped++; continue; }
                $data[] = [
                    'id'             => Str::uuid()->toString(),
                    'transaction_id' => $txUuid,
                    'store_id'       => $storeUuid,
                    'revenue'        => $row[3],
                    'deleted_at'     => $row[4],
                    'created_at'     => $row[5],
                    'updated_at'     => $row[6],
                ];
            }
            if (!empty($data)) {
                DB::table('online_transaction_details')->insert($data);
                $total += count($data);
            }
        }
        $this->line("  ✓ $total online transaction details" . ($skipped ? " ($skipped skipped)" : ''));
    }

    private function importCashFlows(): void
    {
        $this->line('Importing cash flows...');
        // Old columns: id, type, store_id, amount, deleted_at, created_at, updated_at
        $rows = $this->parseRows('cash_flows');
        $total = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            $data = [];
            foreach ($chunk as $row) {
                $data[] = [
                    'id'         => Str::uuid()->toString(),
                    'type'       => $row[1],
                    'store_id'   => $this->storeMap[$row[2]] ?? null,
                    'amount'     => $row[3],
                    'deleted_at' => $row[4],
                    'created_at' => $row[5],
                    'updated_at' => $row[6],
                ];
            }
            DB::table('cash_flows')->insert($data);
            $total += count($data);
        }
        $this->line("  ✓ $total cash flows");
    }

    private function importWifiCredentials(): void
    {
        $this->line('Importing wifi credentials...');
        // Old columns: id, code, is_active, created_at, updated_at
        $rows = $this->parseRows('wifi_credentials');
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id'         => Str::uuid()->toString(),
                'company_id' => $this->companyId,
                'code'       => $row[1],
                'is_active'  => $row[2],
                'created_at' => $row[3],
                'updated_at' => $row[4],
            ];
        }
        if (!empty($data)) DB::table('wifi_credentials')->insertOrIgnore($data);
        $this->line('  ✓ ' . count($data) . ' wifi credentials');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SQL Parser
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Parse all rows from a MySQL dump INSERT statement for the given table.
     * Returns array of arrays (each inner array is one row's values, positional).
     */
    private function parseRows(string $table): array
    {
        $pattern = '/INSERT INTO `' . preg_quote($table, '/') . '` VALUES\s+(.*?);(?:\s*\n|$)/s';
        if (!preg_match($pattern, $this->sql, $matches)) {
            $this->warn("  No INSERT found for table: $table");
            return [];
        }
        return $this->parseValuesList($matches[1]);
    }

    private function parseValuesList(string $valuesStr): array
    {
        $rows = [];
        $i    = 0;
        $len  = strlen($valuesStr);

        while ($i < $len) {
            // skip to opening '('
            while ($i < $len && $valuesStr[$i] !== '(') $i++;
            if ($i >= $len) break;
            $i++; // consume '('

            // parse one row until closing ')'
            $row      = [];
            $val      = '';
            $inStr    = false;

            while ($i < $len) {
                $c = $valuesStr[$i];

                if ($inStr) {
                    if ($c === '\\') {
                        $next = $valuesStr[$i + 1] ?? '';
                        $val .= match ($next) {
                            "'"  => "'",
                            '\\' => '\\',
                            'n'  => "\n",
                            'r'  => "\r",
                            't'  => "\t",
                            '0'  => "\0",
                            '"'  => '"',
                            default => $next,
                        };
                        $i += 2;
                        continue;
                    }
                    if ($c === "'") { $inStr = false; $i++; continue; }
                    $val .= $c;
                } else {
                    if ($c === "'") { $inStr = true; $i++; continue; }
                    if ($c === ',') {
                        $row[] = $this->cast($val);
                        $val   = '';
                        $i++;
                        continue;
                    }
                    if ($c === ')') {
                        $row[] = $this->cast($val);
                        $rows[] = $row;
                        $i++;
                        break;
                    }
                    $val .= $c;
                }
                $i++;
            }
        }

        return $rows;
    }

    private function cast(string $raw): int|float|string|null
    {
        $raw = trim($raw);
        if ($raw === 'NULL') return null;
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }
        return $raw;
    }
}
