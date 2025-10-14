<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            // LONGTEXT can store up to 4GB; suitable for JSON arrays of filenames
            DB::statement('ALTER TABLE items MODIFY file_path LONGTEXT NULL');
        } else {
            // Fallback: use TEXT which is larger than VARCHAR(255)
            Schema::table('items', function (Blueprint $table) {
                $table->text('file_path')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            // Revert to TEXT to avoid truncation risk when collapsing back to VARCHAR
            DB::statement('ALTER TABLE items MODIFY file_path TEXT NULL');
        } else {
            Schema::table('items', function (Blueprint $table) {
                $table->string('file_path')->nullable()->change();
            });
        }
    }
};
