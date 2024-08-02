<?php
// CartTrait.php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait OperationTrait
{

    public function getUserOperatorId()
    {

        return User::where('id', Auth::user()->id ?? 1)->firstOrFail()->value('operator_id');

    }

}
