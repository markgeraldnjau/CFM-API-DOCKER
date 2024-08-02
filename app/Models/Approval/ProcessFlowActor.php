<?php

namespace App\Models\Approval;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProcessFlowActor extends Model
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
    public function processFlowConfiguration(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProcessFlowConfiguration::class);
    }
}
