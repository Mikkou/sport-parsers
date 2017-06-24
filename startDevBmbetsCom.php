<?php

namespace parsersPicksgrail;

use parsersPicksgrail\boards\devbmbetscom\DevBmbetsComParser;

ini_set('display_errors', 1);
set_time_limit(0);

require_once __DIR__ . '/vendor/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/boards/devbmbetscom/urls.php';

$domain = "dev.bmbets.com";
$days = 1;

//TODO придумать что-нибудь с подчеркиваемыми переменными
foreach($urls as $urlOfCategory) {

    $parser = new DevBmbetsComParser(
        $urlOfCategory,
        $domain,
        $days,
        $config
    );

    $parser->start();

}