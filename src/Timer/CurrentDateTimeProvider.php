<?php

declare(strict_types=1);

namespace Archman\Diana\Timer;

class CurrentDateTimeProvider implements DateTimeProviderInterface
{
    private $timeZone = null;

    public function getDateTime(): \DateTime
    {
        $dt = new \DateTime('now');
        if ($this->timeZone) {
            $dt = $dt->setTimezone($this->timeZone);
        }

        return $dt;
    }

    public function setTimeZone(\DateTimeZone $dtz)
    {
        $this->timeZone = $dtz;
    }
}