<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('ssh_setup_token')->nullable()->unique()->after('authorized_keys');
            $table->timestamp('ssh_ready_at')->nullable()->after('ssh_setup_token');
            $table->enum('provision_status', ['pending', 'ssh_setup', 'provisioning', 'provisioned', 'failed'])->default('pending')->after('ssh_ready_at');
            $table->foreignId('provision_task_id')->nullable()->constrained('tasks')->nullOnDelete()->after('provision_status');
            $table->timestamp('provisioned_at')->nullable()->after('provision_task_id');
            $table->string('sites_user')->default('deploy')->after('provisioned_at');
        });
    }
};
