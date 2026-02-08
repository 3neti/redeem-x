<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add indexes to notifications table for improved query performance:
     * - Composite index for notifiable queries (by type and notifiable)
     * - Index for filtering by notification type and date
     * - Index for unread notification queries
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Composite index for querying notifications by notifiable and type
            // Usage: User->notifications()->where('type', 'balance')->get()
            $table->index(['notifiable_type', 'notifiable_id', 'type'], 'notifications_notifiable_type_index');

            // Index for filtering by notification type and creation date
            // Usage: Notification::where('type', 'disbursement_failed')->whereDate('created_at', ...)
            $table->index(['type', 'created_at'], 'notifications_type_created_index');

            // Index for unread notification queries
            // Usage: User->unreadNotifications()->count()
            $table->index('read_at', 'notifications_read_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notifiable_type_index');
            $table->dropIndex('notifications_type_created_index');
            $table->dropIndex('notifications_read_at_index');
        });
    }
};
