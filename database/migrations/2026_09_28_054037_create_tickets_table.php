<?php

declare(strict_types=1);

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\User;
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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('description');
            $table->foreignIdFor(User::class, 'customer_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignIdFor(User::class, 'assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignIdFor(Category::class)
                ->constrained()
                ->restrictOnDelete();
            $table->string('status')->default(TicketStatus::Open);
            $table->string('priority')->default(TicketPriority::Low)->index();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['category_id', 'status']);
            $table->index(['status', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
