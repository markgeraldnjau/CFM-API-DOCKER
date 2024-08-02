<?php

namespace App\Models\Cargo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CargoTracker extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($cargoTracker) {
            $cargoTracker->token = Str::uuid()->toString();
        });
    }

    protected $guarded = [];

    protected array $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function itemTrackers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CargoItemTracker::class, 'cargo_tracker_id');
    }

    public function transaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CargoTransaction::class, 'cargo_transaction_id');
    }


    public function itemTrackerDetails(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->itemTrackers()->select('id', 'token', 'cargo_tracker_id', 'cargo_transaction_item_id', 'actor_type', 'actor_id', 'tracker_status_id', 'tracker_status', 'device_id')
            ->with('transactionItem');
    }
}
