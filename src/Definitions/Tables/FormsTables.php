<?php

declare(strict_types=1);

namespace Nvl\Forms\Definitions\Tables;

use Nvl\Support\Config\PackageStorage;

/**
 * Table name constants for the Forms module.
 */
class FormsTables
{
    public const string Forms = 'nvl_forms_forms';

    public const string I18n = 'nvl_forms_i18n';

    public const string Entries = 'nvl_forms_entries';

    public const string SubmissionReceipts = 'nvl_forms_submission_receipts';

    public const string AllowedOrigins = 'nvl_forms_allowed_origins';

    public const string Analytics = 'nvl_forms_analytics';

    public const string RateLimits = 'nvl_forms_rate_limits';

    public const string FORMS = self::Forms;

    public const string FORM_I18N = self::I18n;

    public const string FORM_ENTRIES = self::Entries;

    public const string FORM_SUBMISSION_RECEIPTS = self::SubmissionReceipts;

    public const string ALLOWED_ORIGINS = self::AllowedOrigins;

    public const string FORM_ANALYTICS = self::Analytics;

    public const string FORM_RATE_LIMITS = self::RateLimits;

    /** Return one configured logical or historical package table. */
    public static function get(string $key): string
    {
        return PackageStorage::resolveTable('forms', $key);
    }
}
