<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(Config::get('teamwork.users_table'), function (Blueprint $table) {
            $table->unsignedBigInteger('current_team_id')->nullable()->index();
        });

        Schema::create(Config::get('teamwork.teams_table'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create(Config::get('teamwork.team_user_table'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained(Config::get('teamwork.users_table'))
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('team_id')
                ->constrained(Config::get('teamwork.teams_table'))
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'team_id']);
        });

        Schema::create(Config::get('teamwork.team_invites_table'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->foreignId('team_id')
                ->constrained(Config::get('teamwork.teams_table'))
                ->cascadeOnDelete();
            $table->enum('type', ['invite', 'request']);
            $table->string('email');
            $table->string('accept_token')->index();
            $table->string('deny_token')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Config::get('teamwork.team_user_table'));
        Schema::dropIfExists(Config::get('teamwork.team_invites_table'));
        Schema::dropIfExists(Config::get('teamwork.teams_table'));

        Schema::table(Config::get('teamwork.users_table'), function (Blueprint $table) {
            $table->dropColumn('current_team_id');
        });
    }
};
