<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Models;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Observers\TicketObserver;
use App\Domain\Ticket\Specifications\TicketSpecification;
use App\Domain\Ticket\States\TicketState;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[ObservedBy([TicketObserver::class])]
#[Fillable(
    'subject',
    'description',
    'status',
    'priority',
    'category_id',
    'customer_id',
    'assigned_to'
)]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function assigne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function state(): TicketState
    {
        return TicketState::for($this);
    }

    public function transitionTo(TicketStatus $targetStatus): self
    {
        return $this->state()->transitionTo($targetStatus);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeMatching(Builder $query, TicketSpecification $specification): Builder
    {
        return $specification->apply($query);
    }
}
