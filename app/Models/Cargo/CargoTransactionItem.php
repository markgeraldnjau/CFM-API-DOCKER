<?php

namespace App\Models\Cargo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CargoTransactionItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected array $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function cargoCategory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CargoCategory::class, 'category_id');
    }

    public function cargoSubCategory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CargoSubCategory::class, 'sub_category_id');
    }

    public function itemTracker(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CargoItemTracker::class, 'cargo_transaction_item_id');
    }

    public function latestItemTracker(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->itemTracker()->latest()->limit(1);
    }
}
