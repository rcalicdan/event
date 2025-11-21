<?php

namespace Tests\Fixtures\Classes\Psr11;

class DependencyService
{
    public bool $called = false;

    public function trigger(): void
    {
        $this->called = true;
    }
}