<?php

declare(strict_types=1);

namespace Tests\Fixtures\Classes\Psr11;

use Rcalicdan\Event\Attributes\ListenTo;

class ContainerListener
{
    public function __construct(
        public DependencyService $service
    ) {
    }

    #[ListenTo('test.psr11')]
    public function handle(): void
    {
        $this->service->trigger();
    }
}
