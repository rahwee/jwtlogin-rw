<?php

namespace App\Http\Tools;

class Factory
{
    function __construct($namespace = '')
    {
        $this->namespace = $namespace;
    }

    public function make($source, array $constructs = array())
    {
        $name = $this->namespace . '\\' . $source;
        if (class_exists($name)) {
            return new $name(...$constructs);
        }
    }
}
