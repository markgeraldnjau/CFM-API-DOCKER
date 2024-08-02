<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CardType extends Model
{
    use HasFactory;


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

    // protected $table = 'card_details';
    protected $table = 'card_types';
    protected $fillable = [
        'id',
        'type_name'
    ];

    public function customerAccount()
    {
        return $this->hasOne(CustomerAccount::class, 'card_id', 'id');
    }

    public function cards(){
        return $this->hasMany(Card::class,'card_type','id');
    }
}
