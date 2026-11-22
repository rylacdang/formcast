<?php

declare(strict_types=1);

namespace Rylacdang\Formcast\Tests\Fixtures\Discovery;

final class PlainHelperClass
{
    public function reference(): string
    {
        return BasicRequest::class;
    }
}
