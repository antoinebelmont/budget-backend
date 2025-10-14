<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    use HasFactory;
    protected $fillable = [
        'category_id', 'type', 'target_amount', 'target_date', 'monthly_amount'
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'monthly_amount' => 'decimal:2'
    ];

    protected $appends = [
        'progress_percentage',
        'remaining_amount',
        'months_remaining',
        'suggested_monthly_amount',
        'current'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function getProgressPercentageAttribute():float
    {
        if (!$this->target_amount || $this->target_amount <= 0) {
            return 0;
        }

        // Get fresh category with transactions
        if (!$this->relationLoaded('transaction')) {
            $this->load('transaction');
        }
        // Force recalculation
        $currentAmount = $this->transaction ? $this->transaction->sum('amount') : 0;

        return min(100, ($currentAmount / $this->target_amount) * 100);
    }

    public function getRemainingAmountAttribute():float
    {
        if (!$this->target_amount) {
            return 0;
        }

        if (!$this->relationLoaded('transaction')) {
            $this->load('transaction');
        };

        return max(0, $this->target_amount - $this->current);
    }

    public function getMonthsRemainingAttribute():float|null
    {
        if (!$this->target_date) {
            return null;
        }

        return round(max(0, now()->diffInMonths($this->target_date, false)));
    }

    public function getSuggestedMonthlyAmountAttribute()
    {
        if (!$this->target_amount || !$this->target_date) {
            return 0;
        }

        $monthsRemaining = $this->months_remaining;
        if ($monthsRemaining <= 0) {
            return 0;
        }
        return number_format($this->remaining_amount / $monthsRemaining,2);
    }

    public function transaction():HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getCurrentAttribute():float
    {
        if (!$this->relationLoaded('transaction')) {
            $this->load('transaction');
        }
        return $this->transaction ? $this->transaction->sum('amount') : 0;
    }
}
