<?php

namespace App\Modules\Wildberries\Exceptions;

use RuntimeException;
use Throwable;

final class WildberriesApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly bool $retryable,
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?int $httpStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode, 0, $previous);
    }

    public static function schemaDrift(): self
    {
        return new self('schema_drift', false);
    }

    public function isAuthorizationFailure(): bool
    {
        return in_array($this->httpStatus, [401, 403], true);
    }

    public function isAuthenticationFailure(): bool
    {
        return $this->httpStatus === 401;
    }
}
