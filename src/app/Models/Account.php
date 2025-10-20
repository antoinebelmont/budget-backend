<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    protected $fillable = ['user_id', 'name', 'type', 'balance', 'closed'];

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions():HasMany
    {
        return $this->hasMany(Transaction::class);
    }
    public function updateBalance():void
    {
        $clearedTransactions = $this->transactions()->where('cleared', 'cleared')->sum('amount');
        $unclearedTransactions = $this->transactions()->where('cleared', 'uncleared')->sum('amount');

        $this->cleared_balance = $clearedTransactions;
        $this->uncleared_balance = $unclearedTransactions;
        $this->balance = $clearedTransactions + $unclearedTransactions;
        $this->save();
    }
}
