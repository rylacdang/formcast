<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class FormcastIgnore {}
