<?php

declare(strict_types=1);

namespace Nvl\Forms\Ownership;

use Nvl\Forms\Models\AllowedOrigin;
use Nvl\Forms\Models\FormAnalytic;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Models\FormRateLimit;
use Nvl\Forms\Models\FormSubmissionReceipt;
use Nvl\Forms\Models\FormTranslation;
use Nvl\Forms\Services\FormModelRegistry;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Support\Ownership\ValueObjects\OwnershipDefinition;
use Nvl\Support\Ownership\ValueObjects\OwnershipResourceDefinition;

/** Compiles the configured Form root and the complete inherited submission graph. @internal */
final readonly class FormsResourceRegistrar
{
    public function __construct(private FormModelRegistry $models) {}

    public function register(OwnershipResourceRegistry $resources): void
    {
        $class = $this->models->formClass();
        $owned = $class::ownershipDefinition()->strategy !== 'unscoped';
        $resources->register(new OwnershipResourceDefinition('forms.forms', 'forms', $class, $class::ownershipDefinition()));
        foreach (['forms.entries' => FormEntry::class, 'forms.receipts' => FormSubmissionReceipt::class, 'forms.origins' => AllowedOrigin::class, 'forms.rates' => FormRateLimit::class, 'forms.analytics' => FormAnalytic::class, 'forms.translations' => FormTranslation::class] as $key => $model) {
            $resources->register(new OwnershipResourceDefinition($key, 'forms', $model, $owned ? OwnershipDefinition::inherited('form') : OwnershipDefinition::unscoped(), parentResource: $owned ? 'forms.forms' : null));
        }
    }
}
