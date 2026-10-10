<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base for every provider-specific driver exception (OpenAI, Gemini, Claude,
 * Groq). Besides the message, it carries WHY the provider failed, which
 * AiManager needs to decide what to do next:
 *  - unreadableResponse: the provider answered, but the content was empty or
 *    not decodable JSON (the AI "saw" the input and couldn't produce a result);
 *  - billingError: the account has no credit / hit a spend limit, so retrying
 *    the same provider is pointless and the reserve must be tried right away.
 * Anything else (network, timeout, invalid key, 5xx) is a plain provider
 * outage.
 */
class AiProviderException extends RuntimeException
{
    private bool $unreadableResponse = false;

    private bool $billingError = false;

    public static function unreadableResponse(string $message, ?Throwable $previous = null): static
    {
        $exception = new static($message, 0, $previous);
        $exception->unreadableResponse = true;

        return $exception;
    }

    public static function billing(string $message, ?Throwable $previous = null): static
    {
        $exception = new static($message, 0, $previous);
        $exception->billingError = true;

        return $exception;
    }

    public function isUnreadableResponse(): bool
    {
        return $this->unreadableResponse;
    }

    public function isBillingError(): bool
    {
        return $this->billingError;
    }
}
