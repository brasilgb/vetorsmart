<?php

namespace App\Services\Websites;

/**
 * Proves control of a custom domain. The production implementation looks up a DNS TXT
 * record; tests replace it, so no real DNS is queried during the test suite.
 */
interface DomainVerifier
{
    public function hasVerificationRecord(string $hostname, string $expectedValue): bool;
}
