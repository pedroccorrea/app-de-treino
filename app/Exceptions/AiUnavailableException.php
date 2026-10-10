<?php

namespace App\Exceptions;

/**
 * Thrown by AiManager when every configured provider failed WITHOUT
 * answering (no credit, invalid/missing key, outage, timeout). It means "the
 * AI service is unavailable right now", never "your input was bad".
 */
class AiUnavailableException extends AiException {}
