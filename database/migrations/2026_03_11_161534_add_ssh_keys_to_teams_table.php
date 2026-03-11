<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->text('ssh_public_key')->nullable()->after('name');
            $table->text('ssh_private_key')->nullable()->after('ssh_public_key');
        });
    }
};
