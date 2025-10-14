<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryGroup extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'name', 'hidden', 'sort_order'];

    protected $casts = [
        'hidden' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function user():BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories():HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort_order');
    }

    public function getTotalBudgetedAttribute():float
    {
        return $this->categories->sum('budgeted');
    }

    public function getTotalActivityAttribute():float
    {
        return $this->categories->sum('activity');
    }

    public function getTotalAvailableAttribute():float
    {
        return $this->categories->sum('available');
    }
}
