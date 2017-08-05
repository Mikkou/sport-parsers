<?php

namespace parsersPicksgrail;

use parsersPicksgrail\boards\devbmbetscom\DevBmbetsComParser;

ini_set('display_errors', 1);
set_time_limit(0);
error_reporting(-1);

require_once __DIR__ . '/vendor/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/boards/devbmbetscom/urls.php';

$domain = "dev.bmbets.com";
$keysForOptions = [
    //ключ для получения с бд кол-ва дней
    "days" => "daysDevBmbetsCom",
    //ключ для получения с бд временной зоны
    "timeZone" => "timeZoneDevBmbetsCom"
];

$DBHelper = helpers\DBHelper::getInstance($domain);

foreach($urls as $urlOfCategory) {

    //создаем объект класса доски
    $parser = new DevBmbetsComParser(
        $urlOfCategory,
        $domain,
        $config,
        $keysForOptions,
        $DBHelper
    );

    //запускаем парсинг
    $parser->start();

}