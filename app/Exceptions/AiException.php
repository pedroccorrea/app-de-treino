<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by AiManager when every configured driver (primary and fallback)
 * fails to fulfil an AI request. The message is already user-safe Portuguese
 * copy, suitable for a flash message or a JSON error payload.
 */
class AiException extends RuntimeException {}
