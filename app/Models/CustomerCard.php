<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerCard extends Model
{
    use HasFactory;

    protected $table = 'card_customers';


    protected $fillable = [
        'customer_ud',
        'card_id',
    ];

    public function card()
    {
        return $this->belongsTo(Card::class, 'card_id', 'id');
    }
    public function customer()
    {
        return $this->belongsTo(Card::class, 'card_id', 'id');
    }
}
