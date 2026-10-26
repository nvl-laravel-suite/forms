<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Presentation;

use Illuminate\Support\Facades\Route;
use Nvl\Forms\Models\Form;

final class FormNavigation
{
    /**
     * Execute the navigation generation.
     *
     * @param  Form  $form  Form model for navigation
     * @param  bool  $overviewActive  Whether the overview tab should be active
     * @return array<int, array{label:string, href:string, active:bool}>
     */
    public function navigation(Form $form, bool $overviewActive): array
    {
        return [['label' => (string) trans('nvl-forms::forms/general.tabs.overview'), 'href' => Route::has('forms.show') ? route('forms.show', $form) : "/forms/{$form->id}", 'active' => $overviewActive]];
    }
}
