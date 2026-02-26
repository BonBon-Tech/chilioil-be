<?php

namespace App\Repository;

use App\Models\Company;
use Illuminate\Database\Eloquent\Collection;

class CompanyRepository
{
    public function all(): Collection
    {
        return Company::withCount('users')->get();
    }

    public function find(int $id): ?Company
    {
        return Company::find($id);
    }

    public function create(array $data): Company
    {
        return Company::create($data);
    }

    public function update(int $id, array $data): ?Company
    {
        $company = Company::find($id);
        if (!$company) {
            return null;
        }
        $company->update($data);
        return $company;
    }

    public function updatePlan(int $id, string $plan): ?Company
    {
        $company = Company::find($id);
        if (!$company) {
            return null;
        }
        $company->update(['plan' => $plan]);
        return $company;
    }
}
