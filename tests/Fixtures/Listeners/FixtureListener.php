<?php

declare(strict_types=1);

namespace Tests\Fixtures\Listeners;

use Rcalicdan\Event\Attributes\Listener;

#[Listener(event: 'fixture.test')]
class FixtureListener
{
    public function handle(): void
    {
        echo 'fixture listener called';
    }
}
