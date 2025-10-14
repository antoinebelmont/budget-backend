<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PhpParser\Node\Attribute;

class Category extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'category_group_id', 'name', 'budgeted', 'color', 'hidden'];

    protected $appends = ['activity','available'];
    public function categoryGroup():BelongsTo
    {
        return $this->belongsTo(CategoryGroup::class);
    }

    public function transactions():HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getActivityAttribute(): float
    {
        // Calculate from database, not stored value
        return $this->transactions()->sum('amount');
    }

    public function getAvailableAttribute()
    {
        return $this->budgeted + $this->activity;
    }

    public function goals():HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function updateActivity():void
    {
        $activity = $this->transactions()->sum('amount');
        $available = $this->budgeted + $activity;

        $this->setAttribute('activity', $activity);
        $this->setAttribute('available', $available);
    }
}
