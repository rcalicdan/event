<?php 

namespace Rcalicdan\Event;

class TestClass
{
    public function boot()
    {
        Event::emit("test");
    }
}