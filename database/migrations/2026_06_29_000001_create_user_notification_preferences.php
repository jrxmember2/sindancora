<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path', 1000)->nullable()->after('document');
            }

            if (! Schema::hasColumn('users', 'avatar_mime_type')) {
                $table->string('avatar_mime_type', 100)->nullable()->after('avatar_path');
            }

            if (! Schema::hasColumn('users', 'avatar_original_filename')) {
                $table->string('avatar_original_filename', 255)->nullable()->after('avatar_mime_type');
            }
        });

        if (! Schema::hasTable('user_notification_preferences')) {
            Schema::create('user_notification_preferences', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('event', 80);
                $table->string('channel', 40);
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->unique(['user_id', 'event', 'channel'], 'user_notification_preferences_unique');
                $table->index(['user_id', 'event']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');

        Schema::table('users', function (Blueprint $table) {
            foreach (['avatar_path', 'avatar_mime_type', 'avatar_original_filename'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
