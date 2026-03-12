<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->text('hook_before_updating_repository')->nullable()->after('repository_branch');
            $table->text('hook_after_updating_repository')->nullable()->after('hook_before_updating_repository');
        });
    }
};
