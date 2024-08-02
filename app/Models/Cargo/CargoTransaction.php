<?php

namespace App\Models\Cargo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CargoTransaction extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($cargoCategory) {
            $cargoCategory->token = Str::uuid()->toString();
        });
    }

    protected $guarded = [];

    protected array $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function transactionItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CargoTransactionItem::class, 'transaction_id');
    }

    public function transactionItemDetails(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->transactionItems()->with('latestItemTracker');
    }

    public function cargoTracker(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CargoTracker::class, 'cargo_transaction_id');
    }

    public function latestCargoTracker(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->cargoTracker()->latest()->limit(1)->first();
    }

}
