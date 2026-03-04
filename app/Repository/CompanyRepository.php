<?php

namespace App\Repository;

use App\Models\Company;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CompanyRepository
{
    public function all(): Collection
    {
        return Company::withCount('users')->get();
    }

    public function paginateWithStats(int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        return Company::withCount(['users', 'transactions'])
            ->when($search, fn($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->orderByDesc('transactions_count')
            ->paginate($perPage);
    }

    public function find(string $id): ?Company
    {
        return Company::find($id);
    }

    public function create(array $data): Company
    {
        return Company::create($data);
    }

    public function update(string $id, array $data): ?Company
    {
        $company = Company::find($id);
        if (!$company) {
            return null;
        }
        $company->update($data);
        return $company;
    }

    public function updatePlan(string $id, string $plan): ?Company
    {
        $company = Company::find($id);
        if (!$company) {
            return null;
        }
        $company->update(['plan' => $plan]);
        return $company;
    }

    public function renew(string $id, int $months): ?Company
    {
        $company = Company::find($id);
        if (!$company) {
            return null;
        }

        // Extend from current expiry if still active, otherwise from now
        $base = ($company->subscription_expires_at && $company->subscription_expires_at->isFuture())
            ? $company->subscription_expires_at->clone()
            : now();

        $company->update([
            'subscription_expires_at' => $base->addMonths($months),
        ]);

        return $company;
    }
}
