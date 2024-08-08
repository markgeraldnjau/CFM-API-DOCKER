<?php
// app/Exceptions/RestApiException.php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class RestApiException extends Exception
{
    protected mixed $statusCode;

    // Predefined common error messages
    protected array $commonMessages = [
        400 => BAD_REQUEST,
        401 => UNAUTHORIZED,
        403 => FORBIDDEN,
        404 => NOT_FOUND,
        422 => UNPROCESSABLE,
        500 => SERVER_ERROR,
    ];

    public function __construct($statusCode = 500, $message = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function render($request)
    {
        $message = $this->getMessage() ?: $this->commonMessages[$this->statusCode] ?? 'Unknown error';

        return new JsonResponse([
            'code' => $this->statusCode,
            'status' => "error",
            'message' => $message,
        ], $this->statusCode);
    }

    /**
     * Validate the status code.
     *
     * @param int $statusCode
     * @return int
     */
    private function validateStatusCode(int $statusCode): int
    {
        // Check if the status code is a valid HTTP status code
        if (!is_int($statusCode) || $statusCode < 100 || $statusCode > 599) {
            return 500; // Default to 500 Internal Server Error
        }

        return $statusCode;
    }
}
