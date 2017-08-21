<?php

namespace parsersPicksgrail;

use parsersPicksgrail\boards\betbraincom\BetbrainComParser;

ini_set('display_errors', 1);
set_time_limit(0);
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

require_once __DIR__ . '/vendor/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/boards/betbraincom/urls.php';

$domain = "betbrain.com";
$keysForOptions = [
    //ключ для получения с бд кол-ва дней
    "days" => "daysBetbrainCom",
    //ключ для получения с бд временной зоны
    "timeZone" => "timeZoneBetbrainCom"
];

$DBHelper = helpers\DBHelper::getInstance($domain);

foreach($urls as $urlOfCategory) {

    //создаем объект класса доски
    $parser = new BetbrainComParser(
        $urlOfCategory,
        $domain,
        $config,
        $keysForOptions,
        $DBHelper
    );

    //запускаем парсинг
    $parser->start();

}