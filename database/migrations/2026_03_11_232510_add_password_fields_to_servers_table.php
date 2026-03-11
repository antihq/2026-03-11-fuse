<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->text('mysql_root_password')->nullable()->after('provisioned_at');
            $table->text('deploy_user_password')->nullable()->after('mysql_root_password');
        });
    }
};
