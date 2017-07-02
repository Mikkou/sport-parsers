<?php

//настрйоки для продакш
define('MAIN_DIR', '/var/www/picksgrail/data/www/parsersPicksgrail'); //main directory
define('HOST', 'localhost'); //server
define('USER', 'picksgrail_admin'); //user
define('PASSWORD', '3V1x6V7b'); //password
define('NAME_BD', 'picksgrail');//base
define('TEST_MOD', '0');//debug status

/*
//локальные данные
define('MAIN_DIR', 'W:/domains/parsersPicksgrail'); //main directory
define('HOST', 'localhost'); //server
define('USER', 'picksgrail_admin'); //user
define('PASSWORD', '1111'); //password
define('NAME_BD', 'picksgrail');//base
define('TEST_MOD', '1');//debug status
*/
$config = [

    //путь к файлу с прокси
    "proxy_file" => MAIN_DIR . '/' . "proxies.txt",
    //путь к списку прокси на сайте сервиса
    "proxy_path_in_service" => "http://api.best-proxies.ru/feeds/proxylist.txt?key=54e145b5466e1fb5f64cbd31f403a3fd&includeType&level=1,2",

];