<?php

declare(strict_types=1);

namespace Nvl\Forms\Events;

/** @api
 * @deprecated Use FormEntryChanged with the versioned scalar payload; removed no earlier than major 6. */
class_alias(FormEntryChanged::class, __NAMESPACE__.'\\FormEntryChangedEvent');
