<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // デフォルトのLaravelユーザーテーブルを削除してセントラルID用に再定義
        Schema::dropIfExists('users');

        Schema::create('configs', function (Blueprint $table) {
            $table->id();
            $table->string('config_name', 191)->unique();
            $table->json('config_value');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->string('central_id', 41)->primary();
            $table->unsignedBigInteger('public_id')->unique();
            $table->string('user_name', 40)->nullable()->default(null);
            $table->dateTime('user_name_updated_at');
            $table->string('display_name', 40)->nullable()->default(null);
            $table->dateTime('display_name_updated_at');
            $table->dateTime('created_at');
            $table->dateTime('deleted_at')->nullable()->default(null);
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->string('central_id', 41)->primary();
            $table->string('full_name', 100)->nullable()->default(null);
            $table->string('country', 100)->nullable()->default(null);
            $table->string('region', 100)->nullable()->default(null);
            $table->text('bio')->nullable()->default(null);
            $table->string('social_account_1', 255)->nullable()->default(null);
            $table->string('social_account_2', 255)->nullable()->default(null);
            $table->string('social_account_3', 255)->nullable()->default(null);
            $table->string('social_account_4', 255)->nullable()->default(null);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->foreign('central_id')->references('central_id')->on('users')->onDelete('cascade');
        });

        Schema::create('user_identities', function (Blueprint $table) {
            $table->string('central_id', 41);
            $table->string('identity_type', 50);
            $table->string('identity', 255);
            $table->string('credential', 255)->nullable()->default(null);
            $table->dateTime('created_at');
            $table->primary(['central_id', 'identity_type']);
            $table->unique(['identity_type', 'identity']);
            $table->foreign('central_id')->references('central_id')->on('users')->onDelete('cascade');
        });

        Schema::create('user_groups', function (Blueprint $table) {
            $table->string('group_id', 100);
            $table->string('central_id', 41);
            $table->dateTime('created_at');
            $table->primary(['group_id', 'central_id']);
            $table->foreign('central_id')->references('central_id')->on('users')->onDelete('cascade');
        });

        Schema::create('user_tags', function (Blueprint $table) {
            $table->string('tag_id', 100);
            $table->string('central_id', 41);
            $table->dateTime('created_at');
            $table->primary(['tag_id', 'central_id']);
            $table->foreign('central_id')->references('central_id')->on('users')->onDelete('cascade');
        });

        Schema::create('tokens', function (Blueprint $table) {
            $table->id();
            $table->string('central_id', 41);
            $table->string('access_token', 512);
            $table->string('refresh_token', 512);
            $table->dateTime('access_token_expires_at');
            $table->dateTime('refresh_token_expires_at');
            $table->dateTime('revoked_at')->nullable()->default(null);
            $table->dateTime('created_at');
            $table->index('central_id');
            $table->foreign('central_id')->references('central_id')->on('users')->onDelete('cascade');
        });

        Schema::create('one_time_passwords', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->char('code', 6);
            $table->tinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable()->default(null);
            $table->dateTime('created_at');
            $table->index('email');
            $table->index('expires_at');
        });

        Schema::create('one_time_codes', function (Blueprint $table) {
            $table->id();
            $table->string('central_id', 41);
            $table->string('auth_code', 128)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable()->default(null);
            $table->dateTime('created_at');
            $table->index('central_id');
            $table->index('expires_at');
            $table->foreign('central_id')->references('central_id')->on('users')->onDelete('cascade');
        });

        Schema::create('one_time_states', function (Blueprint $table) {
            $table->id();
            $table->string('state', 128);
            $table->string('provider', 20);
            $table->string('redirect_uri', 512);
            $table->string('service_id', 36)->nullable()->default(null);
            $table->dateTime('expires_at');
            $table->dateTime('created_at');
            $table->unique(['state', 'provider']);
        });

        Schema::create('user_events', function (Blueprint $table) {
            $table->id();
            $table->string('central_id', 41);
            $table->string('service_id', 36)->nullable()->default(null);
            $table->string('event_type', 50);
            $table->string('identity_type', 50)->nullable()->default(null);
            $table->string('ip_address', 45)->nullable()->default(null);
            $table->text('user_agent')->nullable()->default(null);
            $table->dateTime('created_at');
            $table->index('central_id');
            $table->index('service_id');
            $table->index('event_type');
            $table->index('created_at');
            $table->foreign('central_id')->references('central_id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_events');
        Schema::dropIfExists('one_time_states');
        Schema::dropIfExists('one_time_codes');
        Schema::dropIfExists('one_time_passwords');
        Schema::dropIfExists('tokens');
        Schema::dropIfExists('user_tags');
        Schema::dropIfExists('user_groups');
        Schema::dropIfExists('user_identities');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('configs');
    }
};
