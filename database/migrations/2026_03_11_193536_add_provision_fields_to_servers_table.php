<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('provision_token')->nullable()->unique()->after('authorized_keys');
            $table->string('sites_user')->default('deploy')->after('provision_token');
        });
    }
};
