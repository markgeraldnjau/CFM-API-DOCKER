<?php

namespace App\Models\Cargo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CargoCategory extends Model
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

    public function cargoSubCategories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CargoSubCategory::class, 'category_id');
    }

    public function cargoTransactionItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CargoTransactionItem::class, 'category_id');
    }

}
