<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Date;

class Payee extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'name', 'auto_assign_category_id'];

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function autoAssignCategory():BelongsTo
    {
        return $this->belongsTo(Category::class, 'auto_assign_category_id');
    }

    public function transactions():HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getTotalSpentAttribute():float
    {
        return $this->transactions()->where('is_expense',  1    )->sum('amount');
    }

    public function getAverageTransactionAttribute():float
    {
        return $this->transactions()->avg('amount');
    }

    public function getLastTransactionDateAttribute():Date
    {
        return $this->transactions()->latest('date')->value('date');
    }
}
