<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('id');
            }

            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }

            if (! Schema::hasColumn('users', 'document_type')) {
                $table->string('document_type', 20)->nullable()->after('last_name');
            }

            if (! Schema::hasColumn('users', 'document')) {
                $table->string('document', 50)->nullable()->after('document_type');
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable()->after('document');
            }

            if (! Schema::hasColumn('users', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('remember_token');
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 30)->default('active')->after('trial_ends_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'first_name',
                'last_name',
                'document_type',
                'document',
                'phone',
                'trial_ends_at',
                'status',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};