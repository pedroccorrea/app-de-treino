<?php

namespace App\Exceptions;

/**
 * Thrown by ClaudeDriver whenever the Anthropic API can't be reached,
 * returns a failed HTTP response, or answers with content that isn't
 * decodable JSON.
 */
class ClaudeException extends AiProviderException {}
