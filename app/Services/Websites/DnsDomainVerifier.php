<?php

namespace App\Services\Websites;

class DnsDomainVerifier implements DomainVerifier
{
    /**
     * Looks for the verification TXT record at _vetorsmart-verify.<hostname>.
     */
    public function hasVerificationRecord(string $hostname, string $expectedValue): bool
    {
        $records = @dns_get_record('_vetorsmart-verify.'.$hostname, DNS_TXT);

        if ($records === false) {
            return false;
        }

        foreach ($records as $record) {
            if (($record['txt'] ?? null) === $expectedValue) {
                return true;
            }
        }

        return false;
    }
}
