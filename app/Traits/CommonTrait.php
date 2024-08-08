<?php

namespace App\Traits;

use App\Models\IncidentCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait CommonTrait
{
    private function generateUniqueIncidentCode($length): string
    {
        $characters = 'ABCDEFGHJKLMNOPQRSTUVWXYZ';
        $code = '';
        $charactersLength = strlen($characters);

        try {
            // Generate a random code
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[random_int(0, $charactersLength - 1)];
            }

            // Check uniqueness of the code
            while (IncidentCategory::where('code', $code)->exists()) {
                // If the code already exists, regenerate it
                $code = '';
                for ($i = 0; $i < $length; $i++) {
                    $code .= $characters[random_int(0, $charactersLength - 1)];
                }
            }
        } catch (\Exception $e) {
            Log::error('Error generating unique incident code: ' . $e->getMessage());
            return '';
        }

        return $code;
    }

    function sliceString($string): array
    {
        // Check for the presence of '/'
        if (strpos($string, '/') !== false) {
            list($part1, $part2) = explode('/', $string);
        }
        // Check for the presence of '_'
        elseif (strpos($string, '_') !== false) {
            list($part1, $part2) = explode('_', $string);
        }
        // If neither delimiter is found, return the whole string as part1 and null as part2
        else {
            $part1 = $string;
            $part2 = null;
        }

        return ['from' => $part1, 'to' => $part2];
    }

    public function maskPhoneNumber($phoneNumber): string
    {
        $firstThree = substr($phoneNumber, 0, 3);
        $lastThree = substr($phoneNumber, -3);
        $maskedSection = str_repeat('*', strlen($phoneNumber) - 6);

        return $firstThree . $maskedSection . $lastThree;
    }


    public function errorPayload($exception): array
    {
        return [
            'message'       => $exception->getMessage(),
            'code'          => $exception->getCode(),
            'file'          => $exception->getFile() ?? '',
            'line'          => $exception->getLine() ?? '',
            'trace'         => $exception->getTraceAsString() ?? '',
            'previous'      => $exception->getPrevious() ? $exception->getPrevious()->getMessage() : null,
            'timestamp'     => now()->toDateTimeString() ?? '',
        ];
    }

    public function generateUniqueToken($tableName): string
    {
        do {
            $token = Str::uuid()->toString();
        } while (DB::table($tableName)->where('token', $token)->exists());

        return $token;
    }

    public function validationError($field, $error){
        return [
            'status_code' => HTTP_UNPROCESSABLE_ENTITY,
            'message' => "The given data does not pass validation.",
            'errors' => [
                $field => [$error]
            ]
        ];
    }
}
