<?php

namespace App\Reporting\Connectors;

use App\Models\DataSource;
use Carbon\CarbonImmutable;

/**
 * POS systems differ, so sales are pushed to the source's secret URL
 * (or uploaded as CSV) instead of being pulled.
 */
class PosConnector extends Connector
{
    public function required(): array
    {
        return [];
    }

    public function isConfigured(DataSource $source): bool
    {
        return false;
    }

    public function fetch(DataSource $source, CarbonImmutable $from, CarbonImmutable $to): iterable
    {
        return [];
    }
}
