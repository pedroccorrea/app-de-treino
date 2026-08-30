<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by GroqDriver whenever the Groq API can't be reached, returns a
 * failed HTTP response, or answers with content that isn't decodable JSON.
 */
class GroqException extends RuntimeException {}
