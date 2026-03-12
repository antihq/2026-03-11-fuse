<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('repository_branch');
            $table->timestamp('configured_at')->nullable()->after('status');
        });
    }
};
