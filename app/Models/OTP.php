<?php

namespace App\Models;

use App\Events\SendMail;
use App\Events\SendSms;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OTP extends Model
{
    use HasFactory;
    const EXPIRATION_TIME = 5;
    const DB_ENV = "uat";

    protected $table = 'otps';

    public static function generate($codeLength = 5)
    {
        if (static::DB_ENV == 'uat'){
            $code = 12345;
        } else {
            $min = pow(10, $codeLength);
            $max = $min * 10 - 1;
            $code = random_int($min, $max);
        }
        return $code;
    }

    public function sendCode($code): void
    {
        event(new SendSms(OTP_EVENT, $this->id, ['code' => $code]));
        event(new SendMail(OTP_EVENT, $this->id, ['code' => $code]));
    }


    public function user(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function isValid(): bool
    {
        return !$this->isUsed() && !$this->isExpired();
    }

    public function isUsed()
    {
        return $this->used;
    }

    public function isExpired(): bool
    {
        return $this->updated_at->diffInMinutes(Carbon::now()) > static::EXPIRATION_TIME;
    }
}
