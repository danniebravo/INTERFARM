<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends Model
{
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';

    protected $fillable = [
        'farm_id',
        'type',
        'title',
        'amount',
        'milk_liters_sold',
        'milk_price_per_liter',
        'milk_sale_start_date',
        'milk_sale_end_date',
        'transaction_date',
        'category',
        'payment_method',
        'reference',
        'description',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'milk_liters_sold' => 'decimal:2',
        'milk_price_per_liter' => 'decimal:2',
        'milk_sale_start_date' => 'date',
        'milk_sale_end_date' => 'date',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function isIncome(): bool
    {
        return $this->type === self::TYPE_INCOME;
    }

    public function isExpense(): bool
    {
        return $this->type === self::TYPE_EXPENSE;
    }
}
