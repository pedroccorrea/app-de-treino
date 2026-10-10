<?php

namespace App\Exceptions;

/**
 * Thrown by AiManager when every configured provider failed and at least one
 * of them did answer, but with content that couldn't be turned into a result
 * (empty or invalid JSON) — e.g. the photo couldn't be read.
 */
class AiUnreadableResponseException extends AiException {}
