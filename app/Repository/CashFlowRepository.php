<?php

namespace App\Repository;

use App\Models\CashFlow;

class CashFlowRepository
{
    public function create(array $data): CashFlow
    {
        return CashFlow::create($data);
    }

    public function find(int $id): ?CashFlow
    {
        return CashFlow::find($id);
    }

    public function update(int $id, array $data): ?CashFlow
    {
        $cashFlow = CashFlow::find($id);
        if ($cashFlow) {
            $cashFlow->update($data);
        }
        return $cashFlow;
    }

    public function delete(int $id): bool
    {
        $cashFlow = CashFlow::find($id);
        if ($cashFlow) {
            return $cashFlow->delete();
        }
        return false;
    }

    public function all()
    {
        return CashFlow::all();
    }
}
