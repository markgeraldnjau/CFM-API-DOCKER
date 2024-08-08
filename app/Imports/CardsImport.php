<?php

namespace App\Imports;

use App\Models\Card;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CardsImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     *
     */

    public $card_type;

    public function __construct($card_type)
    {
        $this->card_type = $card_type;
    }
    public function model(array $row)
    {
        return new Card([
            'tag_id' => $row['tag_id'],
            'card_number' => $row['card_number'],
            'status' => $row['status'],
            'dateuploaded' => now()->format('Y-m-d'),
            'card_type' => $this->card_type,
            'expire_date' => now()->format('Y-m-d'),
            'card_ownership' => $row['card_ownership'],
            'credit_type' => $row['credit_type'],
            'company_id' => $row['company_id'],
            'last_update_time' => now()->format('Y-m-d'),
            'card_block_action' => $row['card_block_action'],
            'card_pin' => $row['card_pin'],
        ]);
    }


}
