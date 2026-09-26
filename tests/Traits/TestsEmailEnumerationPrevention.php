<?php

namespace Tests\Traits;

trait TestsEmailEnumerationPrevention
{
    /**
     * Asserts that responses for existing and non-existing emails are identical.
     * Used to verify email enumeration is prevented.
     */
    protected function assertEmailEnumerationPrevented($responseWithExistingEmail, $responseWithNonExistingEmail, int $expectedStatus = 201): void
    {
        $this->assertSame($responseWithExistingEmail->status(), $responseWithNonExistingEmail->status());
        $this->assertSame($responseWithExistingEmail->status(), $expectedStatus);
        $this->assertSame($responseWithExistingEmail->json(), $responseWithNonExistingEmail->json());
    }

    /**
     * Asserts that redirect responses are identical for existing and non-existing emails.
     */
    protected function assertEmailEnumerationPreventedWithRedirect($responseWithExistingEmail, $responseWithNonExistingEmail): void
    {
        $this->assertSame($responseWithExistingEmail->status(), $responseWithNonExistingEmail->status());
        $this->assertSame($responseWithExistingEmail->status(), 302);
        $this->assertSame($responseWithExistingEmail->headers->get('Location'), $responseWithNonExistingEmail->headers->get('Location'));
    }
}
