<?php

namespace Pbmedia\ApiHealth\Tests\TestCheckers;

use Pbmedia\ApiHealth\Checkers\Checker;

class SchedulessChecker implements Checker
{
    public function run()
    {
        return;
    }

    public function isDue(): bool
    {
        return true;
    }

    public static function create()
    {
        return new static;
    }
};
