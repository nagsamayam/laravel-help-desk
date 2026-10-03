<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Ticket::class)
                ->nullable()
                ->constrained('tickets')
                ->cascadeOnDelete();
            $table->foreignIdFor(User::class)
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('disk')->default('public');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
            $table->index(['user_id', 'ticket_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
