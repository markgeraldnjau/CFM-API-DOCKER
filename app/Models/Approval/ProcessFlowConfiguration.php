<?php

namespace App\Models\Approval;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProcessFlowConfiguration extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($approvalProcess) {
            $approvalProcess->token = Str::uuid()->toString();
        });
    }

    protected $guarded = [];

    protected array $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function actors(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProcessFlowActor::class);
    }

    public function approvalProcess(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ApprovalProcess::class);
    }
}
