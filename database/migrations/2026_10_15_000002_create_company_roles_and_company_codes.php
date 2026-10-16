<?php

use App\Models\Company;
use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - companies.company_code: the short code staff type at sign-in (e.g. PIK-4821).
 * - company_roles: each company's roles and what they may do. The company
 *   administrator can edit permissions and add custom roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'company_code')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('company_code', 16)->nullable()->unique()->after('name');
            });
        }

        if (! Schema::hasTable('company_roles')) {
            Schema::create('company_roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('key', 40);
                $table->string('name', 80);
                $table->string('description', 255)->nullable();
                $table->json('permissions');
                $table->boolean('is_system')->default(false);
                $table->timestamps();
                $table->unique(['company_id', 'key']);
            });
        }

        foreach (DB::table('companies')->get() as $c) {
            if (! $c->company_code) {
                DB::table('companies')->where('id', $c->id)->update(['company_code' => Company::makeCompanyCode($c->name)]);
            }
            Permissions::seedDefaultRoles((int) $c->id);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_roles');
        if (Schema::hasColumn('companies', 'company_code')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropUnique(['company_code']);
                $table->dropColumn('company_code');
            });
        }
    }
};
