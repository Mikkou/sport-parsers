<?php

namespace parsersPicksgrail\boards\devbmbetscom;

use parsersPicksgrail\Parser;

class DevBmbetsComParser extends Parser
{
    function __construct($urlOfCategory, $domain, $days)
    {
        parent::__construct($urlOfCategory, $domain, $days);
    }

    public function getUrlsOnEvents()
    {
        // TODO: Implement getUrlsOnEvents() method.
    }

}