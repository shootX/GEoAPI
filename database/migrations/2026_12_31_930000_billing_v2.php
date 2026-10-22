<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing V2 tables only. This file does not ALTER an existing table.
 *
 * Production: platform.* and billing_orders are owned by postgres. The app
 * role carapi_electrik has DML and cannot ALTER those tables. This migration
 * is CREATE TABLE in the application schema. Run it with a role that has
 * CREATE on that schema. If carapi_electrik lacks CREATE, run this migration
 * as the postgres owner. It does not need to own platform.* or billing_orders.
 * Boolean columns are PostgreSQL boolean, not integers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('billing_workspaces')) {
            Schema::create('billing_workspaces', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('owner_user_id')->unique()->constrained('users');
                $table->unsignedBigInteger('legacy_user_id')->unique();
                $table->string('origin', 32);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('billing_workspace_subscriptions')) {
            Schema::create('billing_workspace_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->unique()->constrained('billing_workspaces')->cascadeOnDelete();
                $table->string('plan_code');
                $table->string('interval', 8);
                $table->string('status', 24);
                $table->unsignedInteger('monthly_quota');
                $table->unsignedInteger('rpm');
                $table->unsignedInteger('included_projects');
                $table->dateTime('period_start');
                $table->dateTime('period_end');
                $table->dateTime('term_end');
                $table->string('pending_plan_code')->nullable();
                $table->string('pending_interval', 8)->nullable();
                $table->boolean('legacy_preserved')->default(false);
                $table->boolean('grandfathered')->default(false);
                $table->boolean('unlimited')->default(false);
                $table->boolean('shared_quota')->default(false);
                $table->dateTime('activated_at');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('billing_workspace_entitlements')) {
            Schema::create('billing_workspace_entitlements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained('billing_workspaces')->cascadeOnDelete();
                $table->string('plan_code');
                $table->string('status', 24);
                $table->dateTime('starts_at');
                $table->dateTime('ends_at')->nullable();
                $table->string('source', 32);
                $table->timestamps();
                $table->index(['workspace_id', 'status']);
            });
        }
        if (! Schema::hasTable('billing_period_usage')) {
            Schema::create('billing_period_usage', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained('billing_workspaces')->cascadeOnDelete();
                $table->dateTime('period_start');
                $table->unsignedInteger('used_count')->default(0);
                $table->timestamps();
                $table->unique(['workspace_id', 'period_start']);
            });
        }
        if (! Schema::hasTable('billing_project_period_usage')) {
            Schema::create('billing_project_period_usage', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained('billing_workspaces')->cascadeOnDelete();
                $table->unsignedBigInteger('project_id');
                $table->dateTime('period_start');
                $table->unsignedInteger('used_count')->default(0);
                $table->timestamps();
                $table->unique(['workspace_id', 'project_id', 'period_start']);
                $table->index('project_id');
            });
        }
        if (! Schema::hasTable('billing_credit_ledger')) {
            Schema::create('billing_credit_ledger', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained('billing_workspaces')->cascadeOnDelete();
                $table->unsignedBigInteger('billing_order_id')->nullable();
                $table->string('pack_code', 32);
                $table->unsignedInteger('granted');
                $table->unsignedInteger('remaining');
                $table->dateTime('expires_at');
                $table->timestamps();
                $table->index('billing_order_id');
                $table->index(['workspace_id', 'expires_at']);
            });
        }
        if (! Schema::hasTable('billing_project_addons')) {
            Schema::create('billing_project_addons', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained('billing_workspaces')->cascadeOnDelete();
                $table->unsignedBigInteger('billing_order_id')->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->string('status', 16);
                $table->timestamps();
                $table->index('billing_order_id');
            });
        }
        if (! Schema::hasTable('billing_project_caps')) {
            Schema::create('billing_project_caps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained('billing_workspaces')->cascadeOnDelete();
                $table->unsignedBigInteger('project_id');
                $table->unsignedInteger('monthly_cap')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'project_id']);
            });
        }
        if (! Schema::hasTable('billing_refund_entries')) {
            Schema::create('billing_refund_entries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('billing_order_id');
                $table->string('provider_event_id')->unique();
                $table->decimal('amount', 12, 2);
                $table->string('kind', 16);
                $table->timestamps();
                $table->index('billing_order_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_refund_entries');
        Schema::dropIfExists('billing_project_caps');
        Schema::dropIfExists('billing_project_addons');
        Schema::dropIfExists('billing_credit_ledger');
        Schema::dropIfExists('billing_project_period_usage');
        Schema::dropIfExists('billing_period_usage');
        Schema::dropIfExists('billing_workspace_entitlements');
        Schema::dropIfExists('billing_workspace_subscriptions');
        Schema::dropIfExists('billing_workspaces');
    }
};
