<?php

namespace parsersPicksgrail;

use parsersPicksgrail\boards\devbmbetscom\DevBmbetsComParser;

require_once __DIR__ . '/vendor/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/boards/devbmbetscom/urls.php';

$domain = "dev.bmbets.com";
$days = 3;

foreach($urls as $urlOfCategory) {

    $parser = new DevBmbetsComParser(
        $urlOfCategory,
        $domain,
        $days,
        $config
    );

    $parser->start();

}
