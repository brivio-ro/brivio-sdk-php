<?php

declare(strict_types=1);

namespace Brivio;

/**
 * Thrown for any non-success Brivio API response (the `error` envelope) or
 * transport failure.
 */
final class BrivioException extends \RuntimeException
{
    /**
     * @param array<string, list<string>>|null $details
     */
    public function __construct(
        string $message,
        public readonly string $errorCode = 'INTERNAL_ERROR',
        public readonly int $status = 0,
        public readonly ?array $details = null,
    ) {
        parent::__construct($message);
    }
}
