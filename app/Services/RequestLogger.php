<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class RequestLogger
{
    /**
     * Log the request details to a file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function log(string $jsonMessage)
    {
        $logString = '[' . now() . '] ' . $jsonMessage . PHP_EOL;

        // Specify the path to the log file with date
        $logFilePath = storage_path('logs/requests_' . now()->format('Y-m-d') . '.log');

        // Append log data to the file
        File::append($logFilePath, $logString);
    }
}
