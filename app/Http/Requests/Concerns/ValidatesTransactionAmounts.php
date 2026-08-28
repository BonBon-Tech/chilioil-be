<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesTransactionAmounts
{
    protected function transactionAmountRules(string $presence): array
    {
        return [
            'items.*.qty' => ['bail', $presence, 'integer', 'min:1', 'max:2147483647'],
            'items.*.price' => [
                'bail',
                $presence,
                'numeric',
                'regex:/^\d+(?:\.\d{1,2})?$/',
                'decimal:0,2',
                'min:0',
                'max:9999999999999.99',
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $items = $this->input('items');
            if (!is_array($items) || !array_is_list($items) || $items === []) {
                return;
            }

            $totalCents = 0;
            $totalQty = 0;
            $maxCents = 999999999999999;
            $maxQty = 2147483647;

            foreach ($items as $item) {
                if (!is_array($item)) {
                    return;
                }

                $qty = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => $maxQty],
                ]);
                $priceCents = $this->transactionPriceCents($item['price'] ?? null);
                if ($qty === false || $priceCents === null) {
                    return;
                }

                if ($priceCents > intdiv($maxCents, $qty)) {
                    $validator->errors()->add('items', 'Total harga item melebihi batas penyimpanan.');
                    return;
                }

                $lineCents = $priceCents * $qty;
                if ($totalCents > $maxCents - $lineCents || $totalQty > $maxQty - $qty) {
                    $validator->errors()->add('items', 'Total transaksi melebihi batas penyimpanan.');
                    return;
                }

                $totalCents += $lineCents;
                $totalQty += $qty;
            }
        }];
    }

    private function transactionPriceCents(mixed $value): ?int
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return null;
        }

        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', (string) $value, $parts)) {
            return null;
        }

        if (strlen(ltrim($parts[1], '0')) > 13) {
            return null;
        }

        return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
    }
}
