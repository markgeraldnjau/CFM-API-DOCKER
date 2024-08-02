<?php

namespace App\Traits;

use App\Models\FirebaseUserDevice;
use App\Models\IncidentCategory;

trait CommonTrait
{
    private function generateUniqueIncidentCode($length) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';

        // Generate a random three-letter code
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }

        // Check uniqueness of the code
        while (IncidentCategory::where('code', $code)->exists()) {
            // If the code already exists, regenerate it
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }
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

}
