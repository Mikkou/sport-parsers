<?php

require_once __DIR__ . '/vendor/config.php';
require_once __DIR__ . '/vendor/autoload.php';

$domain = "betbrain.com";

$parser = new \parsersPicksgrail\BetbrainComParser();

$parser->start();

dump(1);
