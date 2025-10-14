<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'account_id', 'category_id', 'payee_id',
        'date', 'amount', 'memo', 'cleared', 'approved', 'is_expense', 'goal_id'
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2'
    ];

    public function goal():BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account():BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category():BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function payee():BelongsTo
    {
        return $this->belongsTo(Payee::class);
    }

    protected static function booted():void
    {
        static::created(function ($transaction) {
            if ($transaction->category) {
                $transaction->category->updateActivity();
            }
            if ($transaction->account) {
                $transaction->account->updateBalance();
            }
        });

        static::updated(function ($transaction) {
            if ($transaction->category) {
                $transaction->category->updateActivity();
            }
            if ($transaction->account) {
                $transaction->account->updateBalance();
            }
        });

        static::deleted(function ($transaction) {
            if ($transaction->category) {
                $transaction->category->updateActivity();
            }
            if ($transaction->account) {
                $transaction->account->updateBalance();
            }
        });

    }

}
