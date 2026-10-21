<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'status', 'total'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'total' => '0.00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total' => 'decimal:2',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === OrderStatus::Confirmed;
    }

    public function hasPayments(): bool
    {
        return $this->payments()->exists();
    }

    /**
     * @param  array<int, array{product_name: string, quantity: int, unit_price: string|float|int}>  $items
     */
    public function replaceItems(array $items): void
    {
        $this->items()->delete();

        $this->items()->createMany($items);

        $this->recalculateTotal();
    }

    public function recalculateTotal(): void
    {
        $total = BigDecimal::zero();

        foreach ($this->items()->get() as $item) {
            $total = $total->plus($item->line_total);
        }

        $this->update([
            'total' => (string) $total->toScale(2, RoundingMode::HalfUp),
        ]);
    }
}
