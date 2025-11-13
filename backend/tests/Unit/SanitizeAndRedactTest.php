<?php

namespace Tests\Unit;

use App\Http\Middleware\SanitizeAndRedact;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * SanitizeAndRedactTest - Unit tests for PII redaction middleware.
 * 
 * Tests:
 * - Email redaction
 * - Phone number redaction
 * - Credit card redaction
 * - SSN redaction
 * - Field-based redaction
 * - Nested data sanitization
 */
class SanitizeAndRedactTest extends TestCase
{
    protected SanitizeAndRedact $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SanitizeAndRedact();
    }

    /**
     * Test email addresses are redacted from text.
     */
    public function test_email_addresses_are_redacted(): void
    {
        $request = Request::create('/api/ai/assistant', 'POST', [
            'message' => 'Contact me at john.doe@example.com for details',
        ]);

        $this->middleware->handle($request, function ($req) {
            $this->assertStringNotContainsString('john.doe@example.com', $req->input('message'));
            $this->assertStringContainsString('[EMAIL_REDACTED]', $req->input('message'));
            return response()->json(['success' => true]);
        });
    }

    /**
     * Test multiple emails are redacted.
     */
    public function test_multiple_emails_are_redacted(): void
    {
        $request = Request::create('/api/ai/assistant', 'POST', [
            'message' => 'Email alice@test.com or bob@example.org',
        ]);

        $this->middleware->handle($request, function ($req) {
            $message = $req->input('message');
            $this->assertStringNotContainsString('alice@test.com', $message);
            $this->assertStringNotContainsString('bob@example.org', $message);
            $this->assertEquals(2, substr_count($message, '[EMAIL_REDACTED]'));
            return response()->json(['success' => true]);
        });
    }

    /**
     * Test phone numbers are redacted.
     */
    public function test_phone_numbers_are_redacted(): void
    {
        $testCases = [
            'Call me at (555) 123-4567',
            'My number is 555-123-4567',
            'Phone: 5551234567',
            'International: +1-555-123-4567',
        ];

        foreach ($testCases as $message) {
            $request = Request::create('/api/ai/assistant', 'POST', [
                'message' => $message,
            ]);

            $this->middleware->handle($request, function ($req) use ($message) {
                $redacted = $req->input('message');
                $this->assertStringContainsString('[PHONE_REDACTED]', $redacted);
                $this->assertNotEquals($message, $redacted);
                return response()->json(['success' => true]);
            });
        }
    }

    /**
     * Test credit card numbers are redacted.
     */
    public function test_credit_cards_are_redacted(): void
    {
        $request = Request::create('/api/ai/assistant', 'POST', [
            'message' => 'My card is 4532-1234-5678-9010',
        ]);

        $this->middleware->handle($request, function ($req) {
            $message = $req->input('message');
            $this->assertStringNotContainsString('4532-1234-5678-9010', $message);
            $this->assertStringContainsString('[CARD_REDACTED]', $message);
            return response()->json(['success' => true]);
        });
    }

    /**
     * Test SSN numbers are redacted.
     */
    public function test_ssn_are_redacted(): void
    {
        $request = Request::create('/api/ai/assistant', 'POST', [
            'message' => 'My SSN is 123-45-6789',
        ]);

        $this->middleware->handle($request, function ($req) {
            $message = $req->input('message');
            $this->assertStringNotContainsString('123-45-6789', $message);
            $this->assertStringContainsString('[SSN_REDACTED]', $message);
            return response()->json(['success' => true]);
        });
    }

    /**
     * Test field-based redaction for email field.
     */
    public function test_email_field_is_redacted(): void
    {
        $request = Request::create('/api/ai/assistant', 'POST', [
            'message' => 'Hello',
            'email' => 'user@example.com',
        ]);

        $this->middleware->handle($request, function ($req) {
            $this->assertEquals('[EMAIL_REDACTED]', $req->input('email'));
            return response()->json(['success' => true]);
        });
    }

    /**
     * Test field-based redaction for phone field.
     */
    public function test_phone_field_is_redacted(): void
    {
        $request = Request::create('/api/ai/assistant', 'POST', [
            'message' => 'Hello',
            'phone' => '555-123-4567',
            'phone_number' => '(555) 987-6543',
        ]);

        $this->middleware->handle($request, function ($req) {
            $this->assertStringStartsWith('55', $req->input('phone'));
            $this->assertStringEndsWith('67', $req->input('phone'));
            $this->assertStringContainsString('*', $req->input('phone'));
            
            $this->assertStringStartsWith('(5', $req->input('phone_number'));
            $this->assertStringContainsString('*', $req->input('phone_number'));
            return response()->json(['success' => true]);
        });
    }

    /**
     * Test nested data is sanitized.
     */
    public function test_nested_data_is_sanitized(): void
    {
        $request = Request::create('/api/ai/assistant', 'POST', [
            'message' => 'Contact john@example.com',
            'user' => [
                'email' => 'alice@test.com',
                'contact' => [
                    'phone' => '555-1234',
                ],
            ],
        ]);

        $this->middleware->handle($request, function ($req) {
            $this->assertStringContainsString('[EMAIL_REDACTED]', $req->input('message'));
            $this->assertEquals('[EMAIL_REDACTED]', $req->input('user.email'));
            $this->assertStringContainsString('*', $req->input('user.contact.phone'));
            return response()->json(['success' => true]);
        });
    }

    /**
     * Test non-sensitive data is not modified.
     */
    public function test_normal_text_is_not_modified(): void
    {
        $message = 'I need a blue suit for a beach wedding';
        $request = Request::create('/api/ai/assistant', 'POST', [
            'message' => $message,
        ]);

        $this->middleware->handle($request, function ($req) use ($message) {
            $this->assertEquals($message, $req->input('message'));
            return response()->json(['success' => true]);
        });
    }

    /**
     * Test middleware only applies to specific routes.
     */
    public function test_middleware_applies_to_ai_routes(): void
    {
        $middleware = new SanitizeAndRedact();

        // Should sanitize AI routes
        $aiRequest = Request::create('/api/ai/assistant', 'POST', [
            'message' => 'test@example.com',
        ]);
        $this->assertTrue($this->invokeMethod($middleware, 'shouldSanitize', [$aiRequest]));

        // Should sanitize JSON requests
        $jsonRequest = Request::create('/api/products', 'POST');
        $jsonRequest->headers->set('Content-Type', 'application/json');
        $this->assertTrue($this->invokeMethod($middleware, 'shouldSanitize', [$jsonRequest]));
    }

    /**
     * Test redaction summary provides correct counts.
     */
    public function test_redaction_summary(): void
    {
        $original = 'Contact alice@test.com at 555-1234 or bob@example.com';
        $redacted = '[EMAIL_REDACTED] at [PHONE_REDACTED] or [EMAIL_REDACTED]';

        $summary = $this->middleware->getRedactionSummary($original, $redacted);

        $this->assertEquals(2, $summary['emails_redacted']);
        $this->assertEquals(1, $summary['phones_redacted']);
        $this->assertEquals(0, $summary['cards_redacted']);
        $this->assertEquals(0, $summary['ssns_redacted']);
    }

    /**
     * Test complex real-world message.
     */
    public function test_complex_real_world_message(): void
    {
        $request = Request::create('/api/ai/assistant', 'POST', [
            'message' => 'I need a suit for my wedding. My email is john@wedding.com and ' .
                        'phone (555) 123-4567. Card ending in 1234-5678-9012-3456.',
        ]);

        $this->middleware->handle($request, function ($req) {
            $message = $req->input('message');
            
            // All PII should be redacted
            $this->assertStringNotContainsString('john@wedding.com', $message);
            $this->assertStringNotContainsString('(555) 123-4567', $message);
            $this->assertStringNotContainsString('1234-5678-9012-3456', $message);
            
            // Redaction markers present
            $this->assertStringContainsString('[EMAIL_REDACTED]', $message);
            $this->assertStringContainsString('[PHONE_REDACTED]', $message);
            $this->assertStringContainsString('[CARD_REDACTED]', $message);
            
            // Non-sensitive content preserved
            $this->assertStringContainsString('suit', $message);
            $this->assertStringContainsString('wedding', $message);
            
            return response()->json(['success' => true]);
        });
    }

    /**
     * Test empty or null values are handled gracefully.
     */
    public function test_empty_values_handled_gracefully(): void
    {
        $request = Request::create('/api/ai/assistant', 'POST', [
            'message' => '',
            'email' => null,
            'phone' => '',
        ]);

        $this->middleware->handle($request, function ($req) {
            $this->assertEquals('', $req->input('message'));
            $this->assertNull($req->input('email'));
            return response()->json(['success' => true]);
        });
    }

    /**
     * Helper method to invoke protected methods for testing.
     */
    protected function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
