<?php

namespace App\Models;

use App\Data\OrderFilterData;
use App\Enums\OrderStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'customer_name',
        'customer_email',
        'idempotency_key',
        'idempotency_request_hash',
        'status',
        'total',
        'invoice_path',
        'image_path',
        'processed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id !== null
            && (int) $this->user_id === (int) $user->getAuthIdentifier();
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->getAuthIdentifier());
    }

    public function scopeFilter(Builder $query, OrderFilterData $filters): Builder
    {
        return $query
            ->when(
                $filters->status !== null,
                fn (Builder $builder): Builder => $builder->where('status', $filters->status->value),
            )
            ->when(
                $filters->customerEmail !== null,
                fn (Builder $builder): Builder => $builder->where('customer_email', $filters->customerEmail),
            );
    }

    public function scopePendingOlderThan(Builder $query, CarbonInterface $cutoff): Builder
    {
        return $query
            ->where('status', OrderStatus::Pending->value)
            ->where('created_at', '<=', $cutoff);
    }
}
