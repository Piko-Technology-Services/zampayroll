<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Departments as real records (name, colour, head, description).
 * Employees keep their department *name* on the employee record, so every
 * existing screen, import and report keeps working; this table is the list
 * HR manages and picks from. Existing names are copied in.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('name', 100);
                $table->string('description', 500)->nullable();
                $table->string('color', 7)->nullable();
                $table->unsignedBigInteger('head_employee_id')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'name']);
            });
        }

        $palette = ['#00742D', '#C8742B', '#2C6E9B', '#7A4E9C', '#B8892B', '#1F8A7A', '#A0473E', '#4F6B2F'];
        $rows = DB::table('employees')->whereNotNull('department')->where('department', '!=', '')
            ->select('company_id', 'department')->distinct()->orderBy('company_id')->orderBy('department')->get();
        $i = [];
        foreach ($rows as $r) {
            $name = trim((string) $r->department);
            if ($name === '' || ! $r->company_id) {
                continue;
            }
            $n = $i[$r->company_id] = ($i[$r->company_id] ?? -1) + 1;
            DB::table('departments')->insertOrIgnore([
                'company_id' => $r->company_id, 'name' => $name, 'color' => $palette[$n % count($palette)],
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
