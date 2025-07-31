<?php

namespace App\Repository;

use App\Models\User;

class UserRepository
{
    public function all()
    {
        return User::with('role')->get();
    }

    public function find($id)
    {
        return User::with('role')->findOrFail($id);
    }

    public function create(array $data)
    {
        return User::create($data);
    }

    public function update($id, array $data)
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $user;
    }

    public function delete($id)
    {
        return User::destroy($id);
    }
}

