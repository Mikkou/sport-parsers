<?php

namespace parsersPicksgrail;

use parsersPicksgrail\boards\oddsportalcom\OddsportalComParser;

ini_set('display_errors', 1);
set_time_limit(0);
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

require_once __DIR__ . '/vendor/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/boards/oddsportalcom/urls.php';

$domain = "oddsportal.com";
$keysForOptions = [
    //ключ для получения с бд кол-ва дней
    "days" => "daysOddsportalCom",
    //ключ для получения с бд временной зоны
    "timeZone" => "timeZoneOddsportalCom"
];

$DBHelper = helpers\DBHelper::getInstance($domain);

foreach($urls as $urlOfCategory) {

    //создаем объект класса доски
    $parser = new OddsportalComParser(
        $urlOfCategory,
        $domain,
        $config,
        $keysForOptions,
        $DBHelper
    );

    //запускаем парсинг
    $parser->start();

}