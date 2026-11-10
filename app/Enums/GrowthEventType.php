<?php

namespace App\Enums;

/**
 * Closed list of events recorded for Meu Crescimento. New types are added here deliberately.
 */
enum GrowthEventType: string
{
    case CustomerCreated = 'customer_created';
    case ServiceCreated = 'service_created';
    case ProfessionalCreated = 'professional_created';
    case WebsiteConfigured = 'website_configured';
    case WebsitePublished = 'website_published';
    case DomainVerified = 'domain_verified';
}
