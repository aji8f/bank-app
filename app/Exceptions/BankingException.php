<?php

namespace App\Exceptions;

use Exception;

class BankingException extends Exception
{
    protected string $errorCode;
    protected int $httpStatus;

    public function __construct(string $message, string $errorCode, int $httpStatus = 422)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function render()
    {
        return response()->json([
            'error' => $this->getMessage(),
            'code'  => $this->errorCode,
        ], $this->httpStatus);
    }

    public static function insufficientBalance(): static
    {
        return new static('Insufficient balance', 'INSUFFICIENT_BALANCE', 422);
    }

    public static function userNotFound(): static
    {
        return new static('User not found', 'USER_NOT_FOUND', 404);
    }

    public static function invalidAmount(): static
    {
        return new static('Invalid amount', 'INVALID_AMOUNT', 422);
    }

    public static function sameUser(): static
    {
        return new static('Cannot transfer to the same account', 'SAME_USER_TRANSFER', 422);
    }
}
