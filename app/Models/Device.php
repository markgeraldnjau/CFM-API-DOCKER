<?php

namespace App\Models;

use App\Models\Operator\OperatorType;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $table = 'device_details';
    protected $guarded = ['id'];

    protected $fillable = [
        'device_type',
        'device_name',
        'device_imei',
        'device_serial',
        'printer_BDA',
        'version',
        'activation_status',
        'station_id',
        'log_off',
        'on_off_ticket_id',
        'operator_type_id',
        'device_next_token',
        'last_token',
        'version_app_update',
        'update_allowed',
        'device_last_token',
        'last_update_time',
        'last_connect',
        'balance_product_id',
        'balance_vendor_id',
    ];

    public function deviceType()
    {
        return $this->belongsTo(DeviceType::class, 'device_type', 'type_id');
    }

    public function trainStation()
    {
        return $this->belongsTo(TrainStation::class, 'train_station_id');
    }

    public function onOffTicket()
    {
        return $this->belongsTo(OnOffTicket::class, 'on_off_ticket_id');
    }

    public function operatorType()
    {
        return $this->belongsTo(OperatorType::class, 'operator_type_id');
    }
}
