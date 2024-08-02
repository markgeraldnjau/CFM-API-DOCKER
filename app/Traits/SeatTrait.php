<?php

namespace App\Traits;

use App\Models\OperatorCollection;
use App\Models\User;
use App\Models\Wagon\Seat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait SeatTrait
{
    public function generateSeat($wagonLayout): bool
    {
        try {
            for ($row = 1; $row <= $wagonLayout->seat_rows; $row++) {
                $aisleInterval = $wagonLayout->aisle_interval ?? 0;

                for ($column = 1; $column <= $wagonLayout->seat_columns; $column++) {
                    $number = ($row - 1) * $wagonLayout->seat_columns + $column;
                    if ($wagonLayout->normal_seats >= $number){
                        $payload = [
                            'wagon_layout_id' => $wagonLayout->id,
                            'row' => $row,
                            'column' => $column,
                            'number' => $number,
                            'seat_number' => $wagonLayout->label . '-' . $number,
                            'has_aisle' => $aisleInterval > 0 && $column % $aisleInterval == 0 && $column != $wagonLayout->seat_columns
                        ];
                        Seat::updateOrCreate($payload);
                    }
                }
            }
            return true;
        }catch (\Exception $e){
            Log::error($e->getMessage());
            return false;
        }
    }
}
