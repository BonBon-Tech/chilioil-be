<?php

namespace App\Repository;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserRepository
{
    private function getCompanyId(): ?int
    {
        $user = Auth::user();
        if ($user && $user->role && $user->role->name === 'owner') {
            return null; // owner sees all
        }
        return $user?->company_id;
    }

    private function scopedQuery()
    {
        $query = User::with('role');
        $companyId = $this->getCompanyId();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        return $query;
    }

    public function all()
    {
        return $this->scopedQuery()->get();
    }

    public function paginate(int $perPage = 15)
    {
        return $this->scopedQuery()->paginate($perPage);
    }

    public function find($id)
    {
        return $this->scopedQuery()->findOrFail($id);
    }

    public function create(array $data)
    {
        $data['company_id'] = $data['company_id'] ?? Auth::user()->company_id;
        return User::create($data);
    }

    public function update($id, array $data)
    {
        $user = $this->scopedQuery()->findOrFail($id);
        $user->update($data);
        return $user;
    }

    public function delete($id)
    {
        $user = $this->scopedQuery()->findOrFail($id);
        return $user->delete();
    }
}
