<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitation_codes', function (Blueprint $table) {
            $table->unsignedTinyInteger('months')->default(1)->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('invitation_codes', function (Blueprint $table) {
            $table->dropColumn('months');
        });
    }
};
