<?php

namespace parsersPicksgrail\boards\devbmbetscom;

use parsersPicksgrail\Parser;
use cloudflare;
use httpProxy;

class DevBmbetsComParser extends Parser
{
    protected $newUrlOfCategory;

    function __construct($urlOfCategory, $domain, $config, $keyForOptions, $DBHelper)
    {
        parent::__construct($urlOfCategory, $domain, $config, $keyForOptions, $DBHelper);
    }

    public function getCookies()
    {
        $cleanDomain = str_replace('.', '', $this->domain);

        $path = getcwd() . "\boards\\" . $cleanDomain . "\cookies.txt";

        return $path;
    }

    public function getHeaders()
    {
        $headers = [
            'User-Agent:Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.109 Safari/537.36',
        ];

        return $headers;
    }

    public function getUrlsOnEvents($url, $forWhatDay)
    {
        //создаем новый урл исключительно для нужного дня
        $url = $this->createUrl($url, $forWhatDay);

        echo $url . "\n";

        $html = $this->getHtmlContentFromUrl($url);

        $arrayPartsGeneralUrl = explode('/', $url);

        preg_match_all('/href="(.+)">/', $html, $arrayUrls);

        $count = count($arrayUrls[1]);

        $arrayEventsUrls = [];

        //собираем все ссылки в единый массив
        for ($i = 0; $i < $count; $i++) {

            $partsOfUrl = explode('/', $arrayUrls[1][$i]);

            if (count($partsOfUrl) > 5 && strpos($arrayUrls[1][$i], $arrayPartsGeneralUrl[4]) !== false) {

                $cleanUrl = "http://" . $this->domain . $arrayUrls[1][$i];

                $arrayEventsUrls[] = html_entity_decode($cleanUrl);

            }
        }

        $arrayEventsCleanUrls = array_unique($arrayEventsUrls);

        $resultArray = [];

        // numerate from 0
        foreach ($arrayEventsCleanUrls as $value) {

            $resultArray[] = $value;

        }

        return $resultArray;
    }

    protected function createUrl($url, $forWhatDay)
    {
        //корректируем урл дня категории, который будем парсить
        if ($forWhatDay === 1) {

            $url .= "/" . date('Ymd');

        } else {

            $url .= "/" . (date('Ymd') + ($forWhatDay - 1));

        }

        $this->newUrlOfCategory = $url;

        return $url;
    }

    protected function getTime($html)
    {
        $beginDate = $this->getBeginDate($html);

        $beginTime = $this->getBeginTime($html);

        $resultTime["date_event"] = $beginDate . " " . $beginTime;

        return $resultTime;
    }

    protected function getBeginDate($html)
    {
        $arrayWithDate = explode('/', $this->newUrlOfCategory);

        $wrongDate = $arrayWithDate[5];

        //достаем год
        $year = substr($wrongDate, 0, 4);

        //достаем месяц
        $month = substr($wrongDate, 4, 2);

        //достаем день
        $day = substr($wrongDate, 6, 4);

        //собираем все в едино
        $resultDate = $year . "-" . $month . "-" . $day;

        return $resultDate;
    }

    protected function getBeginTime($html)
    {
        $object = $this->getHtmlObject($html, '.match-info');

        $time = trim($object[0]->children[0]->children[1]->plaintext);

        // for some categories where another html where have time (tennis, ...)
        if (!$time) {
            $time = trim($object[0]->children[1]->plaintext);
        }

        $resultTime = $time . ":00";

        return $resultTime;
    }

    protected function getTypeSport($html)
    {
        $object = $this->getHtmlObject($html, 'li a span.hidden-480');

        $typeSport['type_sport'] = trim($object[0]->plaintext);

        return $typeSport;

    }

    protected function getCountry($html)
    {
        $country['name'] = $this->getCountryName($html);

        $country['css'] = $this->getCountryCss($html);

        $resultArray['country'] = $country;

        return $resultArray;
    }

    protected function getCountryCss($html)
    {
        $object = $this->getHtmlObject($html, 'ul.breadcrumb li a i.fa');

        $name = trim(str_replace('fa ', '', $object[0]->attr['class']));

        return $name;
    }

    protected function getCountryName($html)
    {
        $object = $this->getHtmlObject($html, 'li a span.hidden-480');

        $name = html_entity_decode(trim($object[1]->plaintext));

        return $name;
    }

    protected function getChampionship($html)
    {
        $result = [];

        //получение названия чемпионата
        $result['name_tournament'] = $this->getChampionshipName($html);

        $result['id_tournament'] = $this->getChampionshipId($html);

        return $result;
    }

    protected function getChampionshipName($html)
    {
        $object = $this->getHtmlObject($html, 'ul.breadcrumb');

        //подстраховка на случай, если порядок в хлебных крошках собьется
        if (count($object[0]->children) === 3) {

            $result = trim($object[0]->children[2]->plaintext);

            return $result;
        }

        return '';
    }

    protected function getChampionshipId($html)
    {
        $arrayWithId = explode('-', $this->urlOnEvent);

        $resultId = (int)array_pop($arrayWithId);

        return $resultId;
    }

    protected function getNameEvent($html)
    {
        $object = $this->getHtmlObject($html, 'title');

        $dirtyName = trim($object[0]->plaintext);

        $arrayWithName = explode(',', $dirtyName);

        $arrayWithCleanNames = explode('vs', $arrayWithName[0]);

        $arrayWithCleanNames[0] = "<span><strong>" . trim($arrayWithCleanNames[0]) . "</span></strong>";
        $arrayWithCleanNames[1] = "<span>" . trim($arrayWithCleanNames[1]) . "</span>";

        $nameEvent['name'] = $arrayWithCleanNames[0] . " - " . $arrayWithCleanNames[1];

        return $nameEvent;
    }

    public function getMarkets($html)
    {
        //получение json со всеми рынками с html
        $dirtyJson = $this->getDirtyJsonMarkets($html);

        $arrayWrongJson = explode(']},{', $dirtyJson);

        $count = count($arrayWrongJson);

        $resultArray = [];

        //получение рынков
        for ($i = 0; $i < $count; $i++) {

            $arraySingleMarket = [];

            $wrongJson = $arrayWrongJson[$i];

            //слегка поправим json, чтобы был рабочий
            $correctJson = $this->correctionJson($wrongJson, $count, $i);

            //переводим с json в читабельный вид
            $data = json_decode($correctJson);

            //вытягиваем имя рынка
            $arraySingleMarket['market_name'] = $data->n;

            //вытягиваем айди рынка
            $arraySingleMarket['market_id'] = $data->id;

            //вытягиваем таймауты рынка
            $arraySingleMarket['time_outs'] = $this->getTimeOuts($data);

            $resultArray[] = $arraySingleMarket;

        }

        $resultArrayMarkets['markets'] = $resultArray;

        return $resultArrayMarkets;
    }

    protected function correctionJson($wrongJson, $count, $i)
    {
        //для json, который оканчивается на пустой массив
        if ($wrongJson{$count - 1} === "[") {
            $wrongJson = $wrongJson . "]}";
        }

        //вначале добавляем скобку, кроме 1-ого элемента в массиве
        if ($i !== 0) {
            $wrongJson = "{" . $wrongJson;
        }

        //вконце добавляем скобки, крое последнего элемента в массиве
        if ($i !== $count - 1) {
            $wrongJson = $wrongJson . "]}";
        }

        return $wrongJson;
    }

    public function getBookmakers($html)
    {
        //получение json со всеми букмейкерами с html
        $json = $this->getJsonBookmakers($html);

        $jsonObject = json_decode($json);

        $arrayBookmakers = [];

        //собираем все конторы
        for ($i = 0; $i < 200; $i++) {

            $arraySingleBookmaker = [];

            //если есть айди
            if (!is_null($jsonObject->$i->id)) {

                $arraySingleBookmaker["id"] = $jsonObject->$i->id;

            }

            //если есть имя
            if (!is_null($jsonObject->$i->name)) {

                $arraySingleBookmaker["name"] = $jsonObject->$i->name;

                //компануем
                $arrayBookmakers[] = $arraySingleBookmaker;

            }
        }

        $resultArray["bookmakers"] = $arrayBookmakers;

        return $resultArray;
    }

    protected function getJsonBookmakers($html)
    {
        $array1 = explode('Global.Bookmakers =  ', $html);

        $array2 = explode('Global.OddFormat = 0;', $array1[1]);

        $json = str_replace(';', '', trim($array2[0]));

        return $json;
    }

    protected function getTimeOuts($data)
    {
        $resultArray = [];

        $count = count($data->t);

        for ($i = 0; $i < $count; $i++) {

            $singleTimeOut = [];

            $singleTimeOut["time_out_name"] = trim($data->t[$i]->n);

            $singleTimeOut["time_out_id"] = trim($data->t[$i]->id);

            $resultArray[] = $singleTimeOut;

        }

        return $resultArray;
    }

    protected function getDirtyJsonMarkets($html)
    {
        //получение необходимого json с исходного html
        $array1 = explode('$("#typetabs").tabcontrol({', $html);

        $array2 = explode('});', $array1[1]);

        $dirtyJson = str_replace('data:  [', '', trim($array2[0]));

        $dirtyJson = str_replace('}]}]', '}]}', $dirtyJson);

        //>преобразование в правильный вид json: замена ковычек на двойные, и добавление их к свойствам
        $dirtyJson = str_replace('\'', '"', $dirtyJson);
        $dirtyJson = str_replace('{n:', '{"n":', $dirtyJson);
        $dirtyJson = str_replace(',k:', ',"k":', $dirtyJson);
        $dirtyJson = str_replace(',id:', ',"id":', $dirtyJson);
        $dirtyJson = str_replace(',sn:', ',"sn":', $dirtyJson);
        $dirtyJson = str_replace(',t:', ',"t":', $dirtyJson);
        //<

        return $dirtyJson;
    }

    protected function putInTournament($event)
    {
        //достаем имя турнира
        $putArray["name"] = $event["name_tournament"];

        //получаем часть ссылки на турнир
        $arrayPartsLink = explode('/', $event["link"]);

        //собираем нужную часть ссылки
        $putArray["link"] = "/" . $arrayPartsLink[3] . "/" . $arrayPartsLink[4] . "/" . $arrayPartsLink[5] . "/";

        //получение айди-индекса с таблицы sport_county2 для столбца "id_sc"
        $link = "/" . $arrayPartsLink[3] . "/" . $arrayPartsLink[4] . "/";
        $arrayId = $this->dbHelper->query("SELECT id FROM sport_country2 WHERE link=(?s)", $link);
        $putArray["id_sc"] = $arrayId[0]["id"];

        //проверка на дубли
        $result = $this->dbHelper->query("SELECT * FROM tournament2 WHERE link=(?s)", $putArray["link"]);

        if (!$result) {
            //записываем все в бд
            $this->dbHelper->query("INSERT INTO tournament2 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
        } else {
            // если такая страна есть, то открываем ее для пользователей
            $this->dbHelper->query("UPDATE tournament2 SET `hide`=0 WHERE `link`=(?s)", $putArray["link"]);
        }
    }

    protected function putInSportCountry($event)
    {
        $arrayPartsLink = explode('/', $event["link"]);

        //собираем нужную часть ссылки
        $partLink = "/" . $arrayPartsLink[3] . "/" . $arrayPartsLink[4] . "/";

        //проверка на дубли
        $result = $this->dbHelper->query("SELECT * FROM sport_country2 WHERE link=(?s)", $partLink);

        if (!$result) {
            //записываем все в бд
            $this->dbHelper->query("INSERT INTO sport_country2 (link) VALUES (?s)", $partLink);
        } else {
            // если такая страна есть, то открываем ее для пользователей
            $this->dbHelper->query("UPDATE sport_country2 SET `hide`=0 WHERE `link`=(?s)", $partLink);
        }
    }

    protected function putInSport($event)
    {
        //вид спорта
        $putArray["name"] = $event["type_sport"];

        //>>>получение ссылки на категорию спорта
        $arrayPartsLink = explode('/', $event["link"]);

        $count = count($arrayPartsLink);

        $newArray = [];

        for ($i = 0; $i < $count; $i++) {

            //оставляем нужные части ссылки
            if ($i < 4) {

                $newArray[] = $arrayPartsLink[$i];

            }
        }

        $putArray["link"] = implode("/", $newArray) . "/";
        //<<<

        // check on dublicate
        $result = $this->dbHelper->query("SELECT * FROM sport2 WHERE name=(?s)", $putArray["name"]);

        if (!$result) {
            // write all in database
            $this->dbHelper->query("INSERT INTO sport2 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
        } else {
            // return sport from hide
            $this->dbHelper->query("UPDATE sport2 SET `hide`=0 WHERE `link`=(?s)", $putArray["link"]);
        }
    }

    protected function putInMarket($event)
    {
        //берем все рынки события
        $arrayMarkets = $event["markets"];

        //считаем их
        $countMarkets = count($arrayMarkets);

        //проходимся по всем рынкам
        for ($i = 0; $i < $countMarkets; $i++) {

            //считаем кол-во таймаутов
            $countPeriods = count($arrayMarkets[$i]["time_outs"]);

            //проходимся по всем таймаутам
            for ($u = 0; $u < $countPeriods; $u++) {

                //формирование массива для записи в бд
                $putArray["id"] = $arrayMarkets[$i]["time_outs"][$u]["time_out_id"];
                $putArray["name"] = $arrayMarkets[$i]["market_name"];
                $putArray["period"] = $arrayMarkets[$i]["time_outs"][$u]["time_out_name"];

                //проверка на дубли
                $result = $this->dbHelper->query("SELECT * FROM market2 WHERE id=(?s)", $putArray["id"]);

                if (!$result) {
                    //записываем все в бд
                    $this->dbHelper->query("INSERT INTO market2 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
                }
            }
        }
    }

    protected function putInEvent($event)
    {
        //объединяем все требуещиеся данные в одно место
        $putArray["date_event"] = $event["date_event"];
        $putArray["name"] = $event["name"];
        $putArray["link"] = $event["link"];

        //>>>получение ссылки на турнир и запрос его айди с таблицы tournament2
        //получаем часть ссылки на турнир
        $arrayPartsLink = explode('/', $event["link"]);
        //собираем нужную часть ссылки
        $link = "/" . $arrayPartsLink[3] . "/" . $arrayPartsLink[4] . "/" . $arrayPartsLink[5] . "/";
        //запрашиваем
        $arrayId = $this->dbHelper->query("SELECT id FROM tournament2 WHERE link=(?s)", $link);
        $putArray["id_tournament"] = $arrayId[0]["id"];
        //<<<

        //удаление старых событий
        $this->dbHelper->query("DELETE FROM event2 WHERE `link`=(?s)", $putArray["link"]);

        //добавление новых
        $this->dbHelper->query("INSERT INTO event2 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
    }

    protected function putInBookmakers($event)
    {
        $count = count($event['bookmakers']);

        for ($i = 0; $i < $count; $i++) {

            $array = $event['bookmakers'][$i];

            $this->dbHelper->query("INSERT INTO bookmaker2 (?#) VALUES (?a)", array_keys($array), array_values($array));

        }
    }

    protected function putInCountry($event)
    {
        $array = $event['country'];

        //проверка на дубли
        $result = $this->dbHelper->query("SELECT name FROM country2 WHERE name=(?s)", $array["name"]);

        if (!$result) {

            $this->dbHelper->query("INSERT INTO country2 (?#) VALUES (?a)", array_keys($array), array_values($array));

        }
    }

    public function getHtmlContentFromUrl($urlOnEvent)
    {
        // parameters for cloudflare class
        $httpProxy   = new httpProxy();
        $httpProxyUA = 'proxyFactory';
        $requestLink = $urlOnEvent;

        $attempts = $this->proxyHelper->getAttempts();

        // here we try to get the html with work proxy
        for ($i = 0; $i < $attempts; $i++) {

            // get random proxy from list
            //$proxy = $this->proxyHelper->getOneProxyFromList();

            // Make this the same user agent you use for other cURL requests in your app
            cloudflare::useUserAgent($httpProxyUA);

            $clearanceCookie = cloudflare::bypass($requestLink);

            // use clearance cookie to bypass page
            $requestPage = $httpProxy->performRequest($requestLink, 'GET', null,
                ['cookies' => $clearanceCookie . "gmt=" . $this->getTimeZone() . ";"]);

            // return real page content for site
            $requestPage = json_decode($requestPage);

            //return html if good responce
            if ($requestPage->status->http_code === 200) {

                return $requestPage->content;

            }

            /*
            // if in list all proxies don't work, replace on new
            if ($i === 9) {

                echo "refresh proxy list \n";

                // get new proxies
                $arrayNewProxy = file_get_contents($this->config["proxy_path_in_service"]);

                // if all proxies don't work
                if (!$arrayNewProxy) { break; }

                // rewrite in file
                $f = fopen($this->config['proxy_file'],'w');
                fwrite($f, $arrayNewProxy);
                fclose($f);

                // begin cycle first
                $i = 0;
            }
            */
        }

        /*
        echo "haven't work proxy in the service!\n";
           */

        return false;

    }

    protected function checkEvent($html, $arrayMergesData)
    {
        // don't put event in database, if don't have forecast, or empty value type sport
        if (empty($arrayMergesData["type_sport"]) || strpos($html, 'No betting markets on this game.') !== false) {

            $arrayMergesData["ignore_event"] = 1;

        }

        return $arrayMergesData;
    }

    protected function putInEventMarkets($event)
    {
        //получаем айдишник события с таблицы, где они хранятся
        $idEvent = $this->dbHelper->query("SELECT id FROM event2 WHERE link=" . "'" . $event["link"] . "'" . " ");

        //берем все рынки события
        $arrayMarkets = $event["markets"];

        //считаем их
        $countMarkets = count($arrayMarkets);

        //проходимся по всем рынкам
        for ($i = 0; $i < $countMarkets; $i++) {

            //считаем кол-во таймаутов
            $countPeriods = count($arrayMarkets[$i]["time_outs"]);

            //проходимся по всем таймаутам
            for ($u = 0; $u < $countPeriods; $u++) {

                //формирование массива для записи в бд
                $putArray["id_event"] = $idEvent[0]["id"];
                $putArray["id_market"] = $arrayMarkets[$i]["time_outs"][$u]["time_out_id"];

                //проверка на дубли
                $result = $this->dbHelper->query("SELECT * FROM event_market2 WHERE `id_event`=(?s) AND `id_market`=(?s)",
                    $putArray["id_event"], $putArray["id_market"]);

                if (!$result) {
                    //записываем все в бд
                    $this->dbHelper->query("INSERT INTO event_market2 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
                }
            }
        }
    }
}