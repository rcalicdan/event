<?php

use Rcalicdan\Event\Event;

uses()->beforeEach(function () {
    Event::reset();
})->in('Feature', 'Unit');