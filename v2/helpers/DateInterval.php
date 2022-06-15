<?php

declare(strict_types=1);

namespace WB\Helpers;

class DateInterval
{
    public function getDateInterval(
        string $interval_spec,
        string $mode = 'sub'
    ): string {
        $date = new \DateTime();

        $interval = new \DateInterval($interval_spec);

        if ($mode === 'sub') {
            $date->sub($interval);
        } else {
            $date->add($interval);
        }

        return $date->format('Y-m-d');
    }
}
