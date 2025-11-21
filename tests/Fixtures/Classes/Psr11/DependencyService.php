<?php

declare(strict_types=1);

namespace Tests\Fixtures\Classes\Psr11;

class DependencyService
{
    public bool $called = false;

    public function trigger(): void
    {
        $this->called = true;
    }
}
