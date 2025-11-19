<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\ListenTo;

#[ListenTo(event: 'fixture.test')]
class FixtureListener
{
    public function handle(): void
    {
        echo 'fixture listener called';
    }
}
