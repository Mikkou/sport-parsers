<?php

namespace parsersPicksgrail\boards\betexplorercom;

use parsersPicksgrail\Parser;

class BetexplorerComParser extends Parser
{
    protected $newUrlOfCategory;

    protected $partHtml;

    protected $forSearchCountry;

    function __construct($urlOfCategory, $domain, $config, $keyForOptions, $DBHelper)
    {
        parent::__construct($urlOfCategory, $domain, $config, $keyForOptions, $DBHelper);
    }

    public function getCookies()
    {
        $cleanDomain = str_replace('.', '', $this->domain);

        $path = MAIN_DIR . "/boards/" . $cleanDomain . "/cookies.txt";

        return $path;
    }

    public function getHeaders()
    {
        $headers = [
            'Accept:application/json, text/javascript, */*; q=0.01',
            'Accept-Language:ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4',
            'Connection:keep-alive',
            'Cookie:js_cookie=1; my_cookie_id=68963694; my_cookie_hash=a3a6cff7d32019c6966525acd7de9e7d; my_timezone=+1; widget_timeStamp=1501437510; widget_pageViewCount=4; _ga=GA1.2.1926125207.1500904424; _gid=GA1.2.1158843575.1501358362',
            'Host:www.betexplorer.com',
            'Referer:http://www.betexplorer.com/tennis/challenger-men-singles/chengdu/barry-s-rawat-s/0KEQy1jk/',
            'User-Agent:Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.115 Safari/537.36',
            'X-Requested-With:XMLHttpRequest',
            'User-Agent:Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.109 Safari/537.36',
        ];

        return $headers;
    }

    public function getUrlsOnEvents($url, $forWhatDay)
    {
        //создаем новый урл исключительно для нужного дня
        $url = $this->createUrl($url, $forWhatDay);

        echo $url . "\n";

        $date = $this->getDateForParse($forWhatDay, "day");

        $html = $this->getHtmlContentFromUrl($url);

        $this->getArrayNamesOfCountry($html, $date);

        //получаем объект из всех актуальных событий
        $object = $this->getHtmlObject($html, 'tbody > tr[data-dt]');

        $countEvents = count($object);

        $arrayEventsUrls = [];

        //собираем все ссылки в единый массив
        for ($i = 0; $i < $countEvents; $i++) {

            $dateOfEvent = $object[$i]->attr["data-dt"];

            $resultsOfMatch = trim($object[$i]->children[2]->plaintext);

            if (strpos($resultsOfMatch, ':') === false && strpos($resultsOfMatch, '.') === false
                && $date === (int)$dateOfEvent
            ) {

                $partOfUrl = $object[$i]->children[0]->children[1]->attr['href'];

                $arrayEventsUrls[] = 'http://www.' . $this->domain . $partOfUrl;

            }
        }

        return $arrayEventsUrls;
    }

    protected function getArrayNamesOfCountry($html, $date)
    {
        $object = $this->getHtmlObject($html, '.table-matches > tbody > tr');

        $countEvents = count($object);

        $arrayEventsUrls = [];

        $country = '';

        //собираем все ссылки в единый массив
        for ($i = 0; $i < $countEvents; $i++) {

            $dateOfEvent = $object[$i]->attr["data-dt"];

            if ($object[$i]->attr["class"] === "js-tournament") {
                $country = $object[$i]->children[0]->children[0]->children[0]->children[0]->attr["alt"];
            }

            $resultsOfMatch = trim($object[$i]->children[2]->plaintext);

            $event = [];

            if (strpos($resultsOfMatch, ':') === false && strpos($resultsOfMatch, '.') === false
                && $date === (int)$dateOfEvent
            ) {

                $partOfUrl = $object[$i]->children[0]->children[1]->attr['href'];

                $event[] = $country;
                $event[] = 'http://www.' . $this->domain . $partOfUrl;

                $arrayEventsUrls[] = $event;

            }
        }

        $this->forSearchCountry = $arrayEventsUrls;

    }

    protected function createUrl($url, $forWhatDay)
    {
        // create url with need date
        if ($forWhatDay !== 1) {

            // get year in format "2017"
            $year = $this->getDateForParse($forWhatDay, "year");

            // get number of month like "7" and check (add 0)
            $month = $this->getDateForParse($forWhatDay, "month");
            $month = ((int)$month < 10) ? "0" . $month : $month;

            // get number of day like "31" and check (add 0)
            $day = $this->getDateForParse($forWhatDay, "day");
            $day = ((int)$day < 10) ? "0" . $day : $day;

            $url .= "?year=" . $year . "&month=" . $month . "&day=" . $day . "";
        }

        $this->newUrlOfCategory = $url;

        return $url;
    }

    protected function getTime($html)
    {
        $object = $this->getHtmlObject($html, '#match-date');

        $stringWithDate = $object[0]->attr['data-dt'];

        $arrayDataDate = explode(',', $stringWithDate);

        $beginDate = $this->getBeginDate($arrayDataDate);

        $beginTime = $this->getBeginTime($arrayDataDate);

        $resultTime["date_event"] = $this->checkDateAndTime($beginDate, $beginTime);

        return $resultTime;
    }

    /**
     * преобразование года, месяца, дня, часа в соответствии с заданным таймаутом
     * этот метод создан только из-за того, что не получается настроить куки для того, чтобы
     * сайт сам отдавал нужные все данные о дате и времени. Поэтому делаем преобразование сами
     * @param string $beginDate
     * @param string  $beginTime
     * @return string
     */
    private function checkDateAndTime($beginDate, $beginTime)
    {
        $needTime = $this->getTimeZone();

        $fixTime = $needTime - 1;

        $arrayPartsTime = explode(":", $beginTime);

        // if need add hours
        if ($fixTime >= 0) {

            // modified format time if happened 24 number
            $arrayPartsTime[0] = ((int)($arrayPartsTime[0] + $fixTime) === 24) ? "00" : $arrayPartsTime[0] + $fixTime ;

            // if a lot happened fix time and date
            if ((int)$arrayPartsTime[0] > 24) {

                // get actual time on following day
                $arrayPartsTime[0] = $arrayPartsTime[0] - 24;

                $arrayPartsDate = explode("-", $beginDate);

                // get separately month and year for check next
                $month = $arrayPartsDate[1];
                $year = $arrayPartsDate[0];

                // get actual following day
                $arrayPartsDate[2] = $arrayPartsDate[2] + 1;

                // get count of days in month for check if we got the big number
                $daysOfMonthForCheck = cal_days_in_month(CAL_GREGORIAN, $month, $year);

                // if big -> change number of month on next
                if ($arrayPartsDate[2] > $daysOfMonthForCheck) {

                    // number next month
                    $arrayPartsDate[2] = $arrayPartsDate[2] - $daysOfMonthForCheck;

                    $arrayPartsDate[1] = $arrayPartsDate[1] + 1;

                    // if very big got number of month
                    if ($arrayPartsDate[1] > 12) {

                        $arrayPartsDate[1] = $arrayPartsDate[1] - 12;

                        // refresh year
                        $arrayPartsDate[0] = $arrayPartsDate[0] + 1;
                    }
                }

                $beginDate = implode('-', $arrayPartsDate);

            }

            // if need reduce time
        } else {
            // if got number with minus change him on plus
            $fixTime = -($fixTime);

            // if we try take away from number which less than the deductible
            if ($fixTime > $arrayPartsTime[0]) {

                // learn the remainder of the subtraction for subtraction from the next day
                $newFixTime = $fixTime - (int)$arrayPartsTime[0];

                $arrayPartsTime[0] = 24 - $newFixTime;

                $arrayPartsDate = explode("-", $beginDate);

                // refresh day
                $arrayPartsDate[2] = (int)$arrayPartsDate[2] - 1;

                if ($arrayPartsDate[2] === 0) {

                    $arrayPartsDate[1] = ($arrayPartsDate[1] - 1 === 0) ? 12 : $arrayPartsDate[1] - 1;

                    $month = $arrayPartsDate[1];
                    $year = $arrayPartsDate[0];

                    $arrayPartsDate[2] = cal_days_in_month(CAL_GREGORIAN, $month, $year);

                }

                $beginDate = implode("-", $arrayPartsDate);

            } else {
                $arrayPartsTime[0] = $arrayPartsTime[0] - $fixTime;
            }
        }

        $arrayPartsTime[0] = ((int)$arrayPartsTime[0] < 10) ? "0" . $arrayPartsTime[0] : $arrayPartsTime[0];

        $beginTime = implode(":", $arrayPartsTime);

        return $beginDate . " " . $beginTime;

    }

    protected function getBeginDate($arrayDataDate)
    {
        //достаем год
        $year = $arrayDataDate[2];

        //достаем месяц
        $month = $arrayDataDate[1];

        //достаем день
        $day = $arrayDataDate[0];

        //собираем все в едино
        $resultDate = $year . "-" . $month . "-" . $day;

        return $resultDate;
    }

    protected function getBeginTime($arrayDataDate)
    {
        $hour = $arrayDataDate[3];

        $min = $arrayDataDate[4];

        if (strlen($hour) === 1) {

            $hour = "0" . $hour;

        }

        $time = $hour . ":" . $min;

        $resultTime = $time . ":00";

        return $resultTime;
    }

    protected function getTypeSport($html)
    {
        $object = $this->getHtmlObject($html, '.list-breadcrumb > li');

        $typeSport['type_sport'] = trim($object[1]->plaintext);

        return $typeSport;

    }

    protected function getCountry($html)
    {
        $country['name'] = $this->getCountryName($html);

        $count = count($this->forSearchCountry);

        $country = '';

        // ищем название страны по урлу события
        for ($i = 0; $i < $count; $i++ ) {

            $url = $this->forSearchCountry[$i][1];

            if ($url === $this->urlOnEvent) {
                $country = $this->forSearchCountry[$i][0];
            }
        }

        $resultArray['country'] = $country;

        return $resultArray;
    }

    protected function getCountryCss($html)
    {
        return '';
    }

    protected function getCountryName($html)
    {
        $object = $this->getHtmlObject($html, '.list-breadcrumb > li');

        $name = html_entity_decode(trim($object[2]->plaintext));

        return $name;
    }

    protected function getChampionship($html)
    {
        $result = [];

        //получение названия чемпионата
        $result['name_tournament'] = $this->getChampionshipName($html);

        return $result;
    }

    protected function getChampionshipName($html)
    {
        $object = $this->getHtmlObject($html, '.list-breadcrumb > li');

        $part1 = html_entity_decode(trim($object[2]->plaintext));
        $part2 = html_entity_decode(trim($object[3]->plaintext));
        $part2 = trim(str_replace(['2017/2018', '2017'], '', $part2));

        $object2 = $this->getHtmlObject($html, '.wrap-section__header__title > a');
        $dirtyPart3 = trim($object2[0]->plaintext);
        if (strpos($dirtyPart3, ', ') !== false) {
            $array = explode(', ', $dirtyPart3);
            $part3 = $array[1];
            $resultName = $part1 . ": " . $part2 . ", " . $part3;
        } else {
            $resultName = $part1 . ": " . $part2;
        }

        return $resultName;
    }

    protected function getChampionshipId($html)
    {
        return '';
    }

    protected function getNameEvent($html)
    {
        $object = $this->getHtmlObject($html, '.list-breadcrumb > li');

        $name = html_entity_decode(trim($object[4]->plaintext));

        $nameEvent['name'] = $name;

        return $nameEvent;
    }

    protected function getHtmlOfMarketsAndBookmakers()
    {
        $arrayPartsUrl = explode('/', $this->urlOnEvent);

        // this for request to get names markets
        if (strpos($this->urlOnEvent, '/baseball/') !== false || strpos($this->urlOnEvent, '/volleyball/')
            !== false || strpos($this->urlOnEvent, '/basketball/') !== false
            || strpos($this->urlOnEvent, '/tennis/') !== false
        ) {

            $idMarket = 'ha';

        } else {

            $idMarket = '1x2';

        }

        $requestUrl = 'http://www.' . $this->domain . '/gres/ajax/matchodds.php?p=0&e=' . $arrayPartsUrl[7] . '&b=' . $idMarket;

        $json = $this->getHtmlContentFromUrl($requestUrl);

        $partHtml = json_decode($json);

        return $partHtml;
    }

    public function getMarkets($html)
    {
        // here we can get html where save data about markets
        $this->partHtml = $this->getHtmlOfMarketsAndBookmakers();

        // try get name and id of markets
        $arrayWrongJson = explode('<div class="wrap-header">', $this->partHtml->odds);

        $arrayTagsLi = explode('</li>', $arrayWrongJson[0]);

        $count = count($arrayTagsLi);

        $resultArray = [];

        for ($i = 0; $i < $count; $i++) {

            $arraySingleMarket = [];

            $dirtyTestForSearch = $arrayTagsLi[$i];

            if (strlen($dirtyTestForSearch) < 50) {
                continue;
            }

            $arraySingleMarket['market_name'] = $this->getMarketName($dirtyTestForSearch);

            $arraySingleMarket['code'] = $this->getMarketId($dirtyTestForSearch);

            // don't parse wrong markets
            if (empty($arraySingleMarket['code'])) {
                continue;
            }

            $resultArray[] = $arraySingleMarket;

        }

        $resultArrayMarkets['markets'] = $resultArray;

        return $resultArrayMarkets;

    }

    protected function getMarketName($dirtyTestForSearch)
    {
        $dirtyText = trim(strip_tags($dirtyTestForSearch));

        $arrayPartsOfName = explode(' ', $dirtyText);

        // this check for one market which name two word in name
        if ($arrayPartsOfName[0] === "1X2" && $arrayPartsOfName[1] === "Odds") {

            return "1X2 Odds";

        } else {

            return $arrayPartsOfName[0];

        }
    }

    protected function getMarketId($dirtyTestForSearch)
    {
        $arrayPartsText = explode(", '", $dirtyTestForSearch);

        if (array_key_exists(2, $arrayPartsText)) {

            $id = str_replace("'", "", $arrayPartsText[2]);

        } else {

            $id = '';

        }

        return $id;
    }

    public function getBookmakers($html)
    {
        $arrayPartsHtml = explode('<tr', $this->partHtml->odds);

        $arrayBookmakers = [];

        $count = count($arrayPartsHtml);

        // cleaning and search need data
        for ($i = 0; $i < $count; $i++) {

            $dirtyText = $arrayPartsHtml[$i];

            if (strlen($dirtyText) < 2000 && strpos($dirtyText, "data-bid") !== false) {

                $arrayWithName = explode("'event-name': '", $dirtyText);

                $arrayWithName2 = explode("', '", $arrayWithName[1]);

                $cleanName = $arrayWithName2[0];

                $arrayBookmakers[]["name"] = $cleanName;

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
        $arrayId = $this->dbHelper->query("SELECT id FROM sport_country3 WHERE link=(?s)", $link);
        $putArray["id_sc"] = $arrayId[0]["id"];

        //проверка на дубли
        $result = $this->dbHelper->query("SELECT * FROM tournament3 WHERE link=(?s)", $putArray["link"]);

        if (!$result) {
            //записываем все в бд
            $this->dbHelper->query("INSERT INTO tournament3 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
        }
    }

    protected function putInSportCountry($event)
    {
        $arrayPartsLink = explode('/', $event["link"]);

        //собираем нужную часть ссылки
        $partLink = "/" . $arrayPartsLink[3] . "/" . $arrayPartsLink[4] . "/";

        $putArray['link'] = $partLink;
        $putArray['id_sport'] = $this->dbHelper->query("SELECT id FROM sport3 WHERE name=(?s)", $event['type_sport'])[0]["id"];
        $putArray['id_country'] = $this->dbHelper->query("SELECT id FROM country3 WHERE name=(?s)", $event['country']['name'])[0]["id"];

        //проверка на дубли
        $result = $this->dbHelper->query("SELECT * FROM sport_country3 WHERE link=(?s)", $partLink);

        if (!$result) {
            //записываем все в бд
            $this->dbHelper->query("INSERT INTO sport_country3 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
        } else {
            // если такая страна есть, то открываем ее для пользователей
            $this->dbHelper->query("UPDATE sport_country3 SET `hide`=0 WHERE `link`=(?s)", $partLink);
        }
    }

    protected function putInSport($event)
    {
        // type sport
        $putArray["name"] = $event["type_sport"];

        //>>> getting link on sport category
        $arrayPartsLink = explode('/', $event["link"]);

        $count = count($arrayPartsLink);

        for ($i = 0; $i < $count; $i++) {

            //оставляем нужные части ссылки
            if ($i === 3) {

                $putArray["link"] = "/" . $arrayPartsLink[$i] . "/";

            }
        }
        //<<<

        $putArray["id_market"] = '';

        // get id of first market (default)
        if ($event["markets"]) {
            $putArray["id_market"] = $event["markets"][0]["code"];
            $arrayResult = $this->dbHelper->query("SELECT id FROM market3 WHERE code=(?s)", $event["markets"][0]["code"]);
            $putArray["id_market"] = $arrayResult[0]["id"];
        }

        //проверка на дубли
        $result = $this->dbHelper->query("SELECT * FROM sport3 WHERE name=(?s)", $putArray["name"]);

        if (!$result) {
            //записываем все в бд
            $this->dbHelper->query("INSERT INTO sport3 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
        } elseif (!empty($putArray["id_market"])) {
            $this->dbHelper->query("UPDATE sport3 SET `hide`=0, `id_market`=(?) WHERE `link`=(?s)", $putArray["id_market"], $putArray["link"]);
        } else {
            // return sport from hide
            $this->dbHelper->query("UPDATE sport3 SET `hide`=0 WHERE `link`=(?s)", $putArray["link"]);
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

            //формирование массива для записи в бд
            $putArray["name"] = $arrayMarkets[$i]["market_name"];
            $putArray["code"] = $arrayMarkets[$i]["code"];

            //проверка на дубли
            $result = $this->dbHelper->query("SELECT * FROM market3 WHERE name=(?s)", $putArray["name"]);

            if (!$result) {
                //записываем все в бд
                $this->dbHelper->query("INSERT INTO market3 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
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
        $arrayId = $this->dbHelper->query("SELECT id FROM tournament3 WHERE link=(?s)", $link);
        $putArray["id_tournament"] = $arrayId[0]["id"];
        //<<<

        //удаление старых событий
        $this->dbHelper->query("DELETE FROM event3 WHERE `link`=(?s)", $putArray["link"]);

        //добавление новых
        $this->dbHelper->query("INSERT INTO event3 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));

    }

    protected function putInBookmakers($event)
    {
        $count = count($event['bookmakers']);

        for ($i = 0; $i < $count; $i++) {

            $array = $event['bookmakers'][$i];

            $this->dbHelper->query("INSERT INTO bookmaker3 (?#) VALUES (?a)", array_keys($array), array_values($array));

        }
    }

    protected function putInCountry($event)
    {
        $array = $event['country'];

        //проверка на дубли
        $result = $this->dbHelper->query("SELECT name FROM country3 WHERE name=(?s)", $array["name"]);

        if (!$result) {

            $this->dbHelper->query("INSERT INTO country3 (?#) VALUES (?a)", array_keys($array), array_values($array));

        }
    }

    protected function checkEvent($html, $arrayMergesData)
    {
        if (empty($arrayMergesData["markets"])) {

            $arrayMergesData["ignore_event"] = 1;

        }

        return $arrayMergesData;
    }

    protected function putInEventMarkets($event)
    {
        //получаем айдишник события с таблицы, где они хранятся
        $idEvent = $this->dbHelper->query("SELECT id FROM event3 WHERE link=" . "'" . $event["link"] . "'" . " ");

        //берем все рынки события
        $arrayMarkets = $event["markets"];

        //считаем их
        $countMarkets = count($arrayMarkets);

        //проходимся по всем рынкам
        for ($i = 0; $i < $countMarkets; $i++) {

            $nameMarket = $arrayMarkets[$i]["market_name"];

            $idMarket = $this->dbHelper->query("SELECT id FROM market3 WHERE name=" . "'" . $nameMarket . "'" . " ");

            //формирование массива для записи в бд
            $putArray["id_event"] = (int)$idEvent[0]["id"];
            $putArray["id_market"] = (int)$idMarket[0]["id"];

            //проверка на дубли
            $result = $this->dbHelper->query("SELECT * FROM event_market3 WHERE `id_event`=(?s) AND `id_market`=(?s)",
                $putArray["id_event"], $putArray["id_market"]);

            if (!$result) {

                //записываем все в бд
                $this->dbHelper->query("INSERT INTO event_market3 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));

            }
        }
    }

    protected function putInSportSport2($event)
    {
        //получаем айдишник вида спорта с таблицы, где они хранятся
        $idSport = $this->dbHelper->query("SELECT id FROM sport3 WHERE `name`=" . "'" . $event["type_sport"] . "'" . " ");

        $this->dbHelper->query("UPDATE sport_sport2 SET `id3`=" . $idSport . " WHERE `name`=" . "'" . $event["type_sport"] . "'");

    }
}