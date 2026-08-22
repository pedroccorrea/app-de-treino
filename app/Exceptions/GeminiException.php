<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by GeminiClient whenever the Gemini API can't be reached, returns a
 * failed HTTP response, or answers with content that isn't decodable JSON.
 * The message is already user-safe Portuguese copy, suitable for a flash
 * message or a JSON error payload.
 */
class GeminiException extends RuntimeException {}
