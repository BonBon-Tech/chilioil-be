<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateGroups = DB::table('stock_opnames')
            ->select('company_id', 'opname_date')
            ->groupBy('company_id', 'opname_date')
            ->havingRaw('COUNT(*) > COUNT(DISTINCT sequence_number)')
            ->get();

        foreach ($duplicateGroups as $group) {
            $rows = DB::table('stock_opnames')
                ->where('company_id', $group->company_id)
                ->where('opname_date', $group->opname_date)
                ->orderBy('sequence_number')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'sequence_number']);
            $next = (int) $rows->max('sequence_number');
            $seen = [];

            foreach ($rows as $row) {
                if (isset($seen[$row->sequence_number])) {
                    DB::table('stock_opnames')
                        ->where('id', $row->id)
                        ->update(['sequence_number' => ++$next]);
                } else {
                    $seen[$row->sequence_number] = true;
                }
            }
        }

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->unique(
                ['company_id', 'opname_date', 'sequence_number'],
                'stock_opnames_company_date_sequence_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropUnique('stock_opnames_company_date_sequence_unique');
        });
    }
};
