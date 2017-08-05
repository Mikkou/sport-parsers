<?php

namespace parsersPicksgrail;

use parsersPicksgrail\boards\betexplorercom\BetexplorerComParser;

ini_set('display_errors', 1);
set_time_limit(0);
error_reporting(-1);

require_once __DIR__ . '/vendor/config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/boards/betexplorercom/urls.php';

$domain = "betexplorer.com";
$keysForOptions = [
    //ключ для получения с бд кол-ва дней
    "days" => "daysBetexplorerCom",
    //ключ для получения с бд временной зоны
    "timeZone" => "timeZoneBetexplorerCom"
];

$DBHelper = helpers\DBHelper::getInstance($domain);

foreach($urls as $urlOfCategory) {

    //создаем объект класса доски
    $parser = new BetexplorerComParser(
        $urlOfCategory,
        $domain,
        $config,
        $keysForOptions,
        $DBHelper
    );

    //запускаем парсинг
    $parser->start();

}