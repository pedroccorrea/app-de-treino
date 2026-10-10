<?php

namespace App\Exceptions;

/**
 * Thrown by OpenAiDriver whenever the OpenAI API can't be reached, returns a
 * failed HTTP response (including credit/spend-limit errors, flagged via
 * isBillingError()), or answers with content that isn't decodable JSON.
 */
class OpenAiException extends AiProviderException {}
