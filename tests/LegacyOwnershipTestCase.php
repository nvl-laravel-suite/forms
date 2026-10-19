<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests;

use Illuminate\Database\Schema\Blueprint;
use Nvl\Forms\Definitions\Tables\FormsTables;
use Nvl\Forms\Services\FormModelRegistry;
use Nvl\Forms\Tests\Fixtures\Company;
use Nvl\Ownership\Services\OwnerIdentityResolver;
use Nvl\Ownership\ValueObjects\OwnershipMapping;

/** Rehearses the complete nonempty legacy Forms submission graph. */
abstract class LegacyOwnershipTestCase extends OwnershipTestCase
{
    public const string FORM = '30000000-0000-4000-8000-000000000001';

    public const string TENANT = '30000000-0000-4000-8000-000000000007';

    protected function reviewedMappings(): array
    {
        $connection = FormModelRegistry::newInstance()->getConnection();
        $schema = $connection->getSchemaBuilder();
        $root = FormModelRegistry::newInstance()->getTable();
        $schema->table($root, static fn (Blueprint $table) => $table->uuid('tenant_id'));
        $schema->table($root, static fn (Blueprint $table) => $table->unique(['tenant_id', 'id']));
        $connection->table($root)->insert(['id' => self::FORM, 'tenant_id' => self::TENANT, 'handle' => 'legacy-contact', 'revision' => 3]);
        $owner = app(OwnerIdentityResolver::class)->reference(Company::findOrFail(7));
        $maps = [new OwnershipMapping('forms.forms', self::FORM, $owner, legacyTenantId: self::TENANT)];
        $records = [
            'forms.translations' => [FormsTables::I18n, ['locale' => 'en', 'name' => 'Legacy contact']],
            'forms.entries' => [FormsTables::Entries, ['email' => 'legacy@example.test', 'submitted_from' => 'https://example.test']],
            'forms.receipts' => [FormsTables::SubmissionReceipts, ['idempotency_key' => 'legacy-retry', 'payload_digest' => str_repeat('a', 64), 'state' => 'completed', 'result_id' => 'legacy-result']],
            'forms.origins' => [FormsTables::AllowedOrigins, ['origin' => 'https://example.test']],
            'forms.rates' => [FormsTables::RateLimits, ['ip_address' => '127.0.0.1', 'window_start' => now(), 'last_submission_at' => now()]],
            'forms.analytics' => [FormsTables::Analytics, ['event_type' => 'view']],
        ];
        $number = 10;
        foreach ($records as $resource => [$identity, $payload]) {
            $table = FormsTables::get($identity);
            $schema->table($table, static fn (Blueprint $blueprint) => $blueprint->uuid('tenant_id'));
            foreach ($schema->getForeignKeys($table) as $foreign) {
                if ($foreign['columns'] === ['form_id']) {
                    $schema->table($table, static fn (Blueprint $blueprint) => $blueprint->dropForeign($foreign['name'] ?? ['form_id']));
                }
            }
            $schema->table($table, static fn (Blueprint $blueprint) => $blueprint->foreign(['tenant_id', 'form_id'])->references(['tenant_id', 'id'])->on($root));
            $id = '30000000-0000-4000-8000-'.str_pad((string) $number++, 12, '0', STR_PAD_LEFT);
            $connection->table($table)->insert(['id' => $id, 'tenant_id' => self::TENANT, 'form_id' => self::FORM, ...$payload]);
            $maps[] = new OwnershipMapping($resource, $id, $owner, legacyTenantId: self::TENANT);
        }

        return $maps;
    }
}
