<?php

namespace Domain\References;

class Date
{
    const MINUTE = 60;
    const HOUR   = 3600;
    const DAY    = 86400;
    const WEEK   = 604800;
    const MONTH  = 2592000;
    const YEAR   = 31536000;

    const NULL_DATETIME = '0000-00-00 00:00:00';
    const NULL_DATE     = '0000-00-00';
    const NULL_TIME     = '00:00:00';
    const DATE          = 'Y-m-d';
    const DATETIME      = 'Y-m-d H:i:s';
    const DATE_RUS      = 'd.m.Y';
    const DATETIME_RUS  = 'd.m.Y H:i';
    const TIME          = 'H:i:s';
}
