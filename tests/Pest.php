<?php

declare(strict_types=1);

use Rcalicdan\Event\Event;

uses()->beforeEach(function () {
    Event::reset();
})->in('Feature', 'Unit');
