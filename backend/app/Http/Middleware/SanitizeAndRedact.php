<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SanitizeAndRedact Middleware
 * 
 * Sanitizes and redacts PII (Personally Identifiable Information) from request data
 * before it reaches controllers or external APIs.
 * 
 * Redacts:
 * - Email addresses
 * - Phone numbers (various formats)
 * - Credit card numbers
 * - SSN/Tax IDs
 */
class SanitizeAndRedact
{
    /**
     * Fields that should be redacted.
     */
    protected array $redactableFields = [
        'email',
        'phone',
        'phone_number',
        'mobile',
        'credit_card',
        'card_number',
        'ssn',
        'tax_id',
    ];

    /**
     * Patterns for automatic PII detection and redaction.
     */
    protected array $patterns = [
        // Email pattern: user@domain.com -> [EMAIL_REDACTED]
        'email' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        
        // Phone patterns (US/International)
        // (123) 456-7890, 123-456-7890, +1-123-456-7890, 1234567890
        'phone' => '/\b(\+?1[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}\b/',
        
        // Credit card (basic pattern for common formats)
        'credit_card' => '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',
        
        // SSN: 123-45-6789
        'ssn' => '/\b\d{3}-\d{2}-\d{4}\b/',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only sanitize for specific routes or content types
        if ($this->shouldSanitize($request)) {
            $this->sanitizeRequest($request);
        }

        return $next($request);
    }

    /**
     * Determine if the request should be sanitized.
     */
    protected function shouldSanitize(Request $request): bool
    {
        // Sanitize AI assistant requests and any JSON requests
        $path = $request->path();
        
        return str_contains($path, 'ai/assistant') ||
               str_contains($path, 'ai/recommendations') ||
               $request->isJson();
    }

    /**
     * Sanitize the request data.
     */
    protected function sanitizeRequest(Request $request): void
    {
        $data = $request->all();
        
        if (empty($data)) {
            return;
        }

        $sanitized = $this->sanitizeData($data);
        
        // Replace request data with sanitized version
        $request->merge($sanitized);
    }

    /**
     * Recursively sanitize data array.
     */
    protected function sanitizeData(mixed $data): mixed
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                // Check if field name indicates PII
                if ($this->isRedactableField($key)) {
                    $sanitized[$key] = $this->redactValue($value);
                } else if (is_array($value) || is_string($value)) {
                    $sanitized[$key] = $this->sanitizeData($value);
                } else {
                    $sanitized[$key] = $value;
                }
            }
            return $sanitized;
        }

        if (is_string($data)) {
            return $this->redactPII($data);
        }

        return $data;
    }

    /**
     * Check if field name indicates redactable data.
     */
    protected function isRedactableField(string $fieldName): bool
    {
        $normalized = strtolower($fieldName);
        
        foreach ($this->redactableFields as $field) {
            if (str_contains($normalized, $field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redact a specific field value.
     */
    protected function redactValue(mixed $value): mixed
    {
        // Don't redact null or empty values
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_string($value) && !empty($value)) {
            // Keep first 2 characters for context
            if (str_contains($value, '@')) {
                return '[EMAIL_REDACTED]';
            }
            
            $length = strlen($value);
            if ($length > 4) {
                return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
            }
            
            return str_repeat('*', $length);
        }

        return '[REDACTED]';
    }

    /**
     * Redact PII from text content using pattern matching.
     */
    protected function redactPII(string $text): string
    {
        $redacted = $text;

        // Redact emails
        $redacted = preg_replace(
            $this->patterns['email'],
            '[EMAIL_REDACTED]',
            $redacted
        );

        // Redact phone numbers
        $redacted = preg_replace(
            $this->patterns['phone'],
            '[PHONE_REDACTED]',
            $redacted
        );

        // Redact credit cards
        $redacted = preg_replace(
            $this->patterns['credit_card'],
            '[CARD_REDACTED]',
            $redacted
        );

        // Redact SSN
        $redacted = preg_replace(
            $this->patterns['ssn'],
            '[SSN_REDACTED]',
            $redacted
        );

        return $redacted;
    }

    /**
     * Get redaction summary for logging/debugging.
     */
    public function getRedactionSummary(string $original, string $redacted): array
    {
        return [
            'emails_redacted' => substr_count($redacted, '[EMAIL_REDACTED]'),
            'phones_redacted' => substr_count($redacted, '[PHONE_REDACTED]'),
            'cards_redacted' => substr_count($redacted, '[CARD_REDACTED]'),
            'ssns_redacted' => substr_count($redacted, '[SSN_REDACTED]'),
            'original_length' => strlen($original),
            'redacted_length' => strlen($redacted),
        ];
    }
}
