<?php

declare(strict_types=1);

namespace Nvl\Forms\Exceptions;

use RuntimeException;

/** @internal Stops Eloquent's deleted event when a conditional retention DELETE matched nothing. */
final class FormRetentionPredicateChanged extends RuntimeException {}
