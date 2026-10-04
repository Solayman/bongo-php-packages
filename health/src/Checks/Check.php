<?php

declare(strict_types=1);

namespace Bongo\Packages\Health\Checks;

abstract class Check
{
    /**
     * Unique name identifying this health check.
     */
    abstract public function name(): string;

    /**
     * Run the diagnostic check and return a CheckResult.
     */
    abstract public function run(): CheckResult;
}
