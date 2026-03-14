<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('database_name')->nullable();
            $table->string('database_user')->nullable();
            $table->text('database_password')->nullable();
            $table->timestamp('database_created_at')->nullable();
        });
    }
};
