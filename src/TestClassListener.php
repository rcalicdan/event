<?php

namespace Rcalicdan\Event;

class TestClassListener
{
    public function __construct()
    {
        Event::on("test", function () {
           echo "test";
        });
    }
}
