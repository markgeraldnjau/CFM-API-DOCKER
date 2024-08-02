<?php

namespace App\Traits;

use App\Models\Operator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\Concerns\Has;

trait OperatorTrait
{
    public function createOperator($request)
    {
        // Generate a unique 6-digit operator_no
        $operatorNo = $this->generateOperatorNo();

        $operator = Operator::updateOrCreate([
            'full_name' => $request->full_name,
            'operator_no' => $operatorNo,
            'username' => $request->username,
            'phone' => $request->phone,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'train_line_id' => $request->train_line_id,
            'operator_category_id' => $request->operator_category_id,
            'operator_type_code' => $request->operator_type_code,
            'station_id' => $request->station_id,
        ]);

        if (!$operator){
            return false;
        }

        $operatorAccount = $this->createOperatorAccount($operator->id);

        if (!$operatorAccount){
            return false;
        }

        return true;

    }

    public function createOperatorAccount($operatorId)
    {
        return Operator\OperatorAccount::updateOrCreate([
            'operator_id' => $operatorId
        ]);
    }

    public function updateOperator($request, $id)
    {

        $operator = Operator::where('id', $id)->update([
            'full_name' => $request->full_name,
            'username' => $request->username,
            'phone' => $request->phone,
            'email' => $request->email,
            'train_line_id' => $request->train_line_id,
            'operator_category_id' => $request->operator_category_id,
            'operator_type_code' => $request->operator_type_code,
            'station_id' => $request->station_id,
        ]);

        if (!$operator){
            return false;
        }
        return true;
    }


    /**
     * Generate a unique 4-digit operator number.
     *
     * @return string
     */
    protected function generateOperatorNo(): string
    {
        // Get the last operator's number
        $lastOperator = Operator::orderBy('operator_no', 'desc')->first();

        if ($lastOperator) {
            // Increment the last operator number by 1
            $lastNumber = intval($lastOperator->operator_no);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            // Start from 000001 if no operators exist
            $newNumber = '000001';
        }

        return $newNumber;
    }
}
