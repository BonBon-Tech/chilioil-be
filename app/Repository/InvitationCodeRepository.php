<?php

namespace App\Repository;

use App\Models\InvitationCode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class InvitationCodeRepository
{
    public function all(): Collection
    {
        return InvitationCode::with('usedByUser')->orderBy('created_at', 'desc')->get();
    }

    public function findByCode(string $code): ?InvitationCode
    {
        return InvitationCode::where('code', $code)->first();
    }

    public function findUnusedByCode(string $code): ?InvitationCode
    {
        return InvitationCode::where('code', $code)->where('is_used', false)->first();
    }

    public function generate(): InvitationCode
    {
        $code = strtoupper(Str::random(8));

        // Ensure uniqueness
        while (InvitationCode::where('code', $code)->exists()) {
            $code = strtoupper(Str::random(8));
        }

        return InvitationCode::create([
            'code' => $code,
            'is_used' => false,
        ]);
    }

    public function markAsUsed(InvitationCode $invitationCode, int $userId): void
    {
        $invitationCode->update([
            'is_used' => true,
            'used_by' => $userId,
            'used_at' => now(),
        ]);
    }

    public function delete(int $id): bool
    {
        $code = InvitationCode::where('id', $id)->where('is_used', false)->first();
        if (!$code) {
            return false;
        }
        return $code->delete();
    }
}
