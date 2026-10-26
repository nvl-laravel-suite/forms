<?php

test('submission context accepts legacy public site attributes while canonical presence remains authoritative', function (): void {
    $request = Request::create('https://legacy.forms.test/submit', 'POST');
    $legacy = new TenantSiteContext(new TenantId('10000000-0000-4000-8000-000000000001'), 'legacy', 'https://legacy.forms.test');
    $request->attributes->set(TenantSiteAttributes::LegacyKey, $legacy);
    expect(formSubmissionContext($request)->publicSite)->toBe('legacy');
    $canonical = new TenantSiteContext($legacy->tenantId, 'canonical', 'https://canonical.forms.test');
    $request->attributes->set(TenantSiteContext::class, $canonical);
    expect(formSubmissionContext($request)->publicSite)->toBe('canonical');
    $request->attributes->set(TenantSiteContext::class, null);
    expect(formSubmissionContext($request)->publicSite)->toBeNull();
});
