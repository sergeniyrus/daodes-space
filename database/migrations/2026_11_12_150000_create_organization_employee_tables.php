<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_employees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone', 20)->unique();
            $table->string('password');
            $table->timestamp('phone_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_employee_memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('organization_employees')
                ->cascadeOnDelete();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('role', 50)->default('employee');
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(
                ['organization_id', 'employee_id'],
                'org_employee_membership_unique'
            );

            $table->index(
                ['organization_id', 'status', 'role'],
                'org_employee_membership_access_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_employee_memberships');
        Schema::dropIfExists('organization_employees');
    }
};
