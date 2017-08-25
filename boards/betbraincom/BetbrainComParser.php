<?php

namespace parsersPicksgrail\boards\betbraincom;

use parsersPicksgrail\Parser;

class BetbrainComParser extends Parser
{
    protected $partHtml;
    protected $hash = '3d4789a3abe84954b92889bf0bad28bb';
    protected $jSessionId = 'A166C70D5C6B5CAB6765D89566F3B61A';

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
            ':authority:bbfeapi.betbrain.com',
            ':method:GET',
            ':scheme:https',
            'accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'accept-language:ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4',
            'cache-control:max-age=0',
            'upgrade-insecure-requests:1',
            'user-agent:Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36',
            'cookie: JSESSIONID=' . $this->jSessionId . '; gmtTimezoneOffset=0',
        ];

        return $headers;
    }

    public function getUrlsOnEvents($typeSport, $forWhatDay)
    {
        $needDay = (int)$this->getDateForParse($forWhatDay, "day");
        $arrayCountries = $this->getAllCountriesWithEvents($typeSport);
        $arrayResultEventsUrls = [];

        $arrayCountries = ['montenegro'];

        $countCountries = count($arrayCountries);
        echo $countCountries . " get countries from single sport. \n";
        for ($i = 0; $i < $countCountries; $i++) {

            $urlForGettingEventsFromCountry = 'https://bbfeapi.' . $this->domain . '/countryOverview/' .
                $typeSport . '/' . $arrayCountries[$i] . '?requestId=5&wsTrack=' . $this->hash
                . '&pageIndexStart=1&pageIndexEnd=2000&domain=www.' . $this->domain . '&method=get';

            $objectEvents = $this->getHtmlContentFromUrl($urlForGettingEventsFromCountry);
            $arrayEvents = $objectEvents->data->eventListItems;
            $countEvents = count($arrayEvents);
            echo "\n" . ($i + 1) . " - " . $arrayCountries[$i];
            for ($r = 0; $r < $countEvents; $r++) {
                // get number of event day
                $dayOfEvent = (int)explode('-', $arrayEvents[$r]->startDate)[2];
                // filter events where not need day and not end
                if (is_null($arrayEvents[$r]->scores) && $dayOfEvent === $needDay) {
                    $numberEvent = $r;
                    $numberCountry = $i;
                    $urlOnEvent = $this->createUrlOnEvent($arrayEvents, $numberEvent, $typeSport, $arrayCountries, $numberCountry);
                    $arrayResultEventsUrls[] = $urlOnEvent;
                }
            }
        }

        return $arrayResultEventsUrls;
    }

    protected function createUrlOnEvent($arrayEvents, $numberEvent, $typeSport, $arrayCountries, $numberCountry)
    {
        $tournamentName = $arrayEvents[$numberEvent]->urlTournamentName;
        $matchName = $arrayEvents[$numberEvent]->urlMatchName;
        $betTypeName = $arrayEvents[$numberEvent]->urlBetTypeName;
        $eventPartName = $arrayEvents[$numberEvent]->urlEventPartName;
        $urlOnEvent = 'https://www.' . $this->domain . '/' . $typeSport . '/' . $arrayCountries[$numberCountry] .
            '/' . $tournamentName . '/' . $matchName . '/#/' . $betTypeName . '/' . $eventPartName . '/';
        return $urlOnEvent;
    }

    protected function getAllCountriesWithEvents($nameOfSport)
    {
        $urlForGettingCountries = 'https://bbfeapi.betbrain.com/sportOverview/' . $nameOfSport . '?requestId=5&wsTrack=' .
            $this->hash . '&domain=www.' . $this->domain . '&method=get';
        $jsonCountries = $this->getHtmlContentFromUrl($urlForGettingCountries);
        $arrayCountries = [];

        if (array_key_exists('countryIdToCountryStatistics', $jsonCountries->data)) {
            $objectCountries = $jsonCountries->data->countryIdToCountryStatistics;
            foreach ($objectCountries as $key => $objectSingleCountry) {
                $arrayCountries[] = $objectCountries->$key->urlCountryName;
            }
        }

        return $arrayCountries;
    }

    public function getHtmlContentFromUrl($parseUrl)
    {
        $headers = $this->getHeaders();

        sleep(1);
        $ch = curl_init($parseUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($result);

        //формирование ответа
        if ($httpCode === 301 || $httpCode === 302) {
            //редиректы
            echo "A redirect has occurred!\n";
            return false;
        } elseif ($httpCode === 404) {
            //страницы не существует
            return false;
        } elseif ($httpCode === 200) {
            return $result;
        } else {
            dump($httpCode);
            dump($result);
            $this->hash = $this->getHtmlContentFromUrl('https://bbfeapi.betbrain.com/httphs?method=get')->wsTrack;
            die;
            return $this->proxyHelper->getHtmlContentFromUrlWithProxy($parseUrl, $cookies, $headers, $this->domain);
        }
    }

    protected function getTime($jObj)
    {
        $resultTime = [];

        foreach ($jObj->data->event as $key => $obj) {
            $resultTime["date_event"] = $obj->startTime;
        }

        return $resultTime;
    }

//    /**
//     * преобразование года, месяца, дня, часа в соответствии с заданным таймаутом
//     * этот метод создан только из-за того, что не получается настроить куки для того, чтобы
//     * сайт сам отдавал нужные все данные о дате и времени. Поэтому делаем преобразование сами
//     * @param string $beginDate
//     * @param string $beginTime
//     * @return string
//     */
//    private function checkDateAndTime($beginDate, $beginTime)
//    {
//        $needTime = $this->getTimeZone();
//
//        $fixTime = $needTime - 1;
//
//        $arrayPartsTime = explode(":", $beginTime);
//
//        // if need add hours
//        if ($fixTime >= 0) {
//
//            // modified format time if happened 24 number
//            $arrayPartsTime[0] = ((int)($arrayPartsTime[0] + $fixTime) === 24) ? "00" : $arrayPartsTime[0] + $fixTime;
//
//            // if a lot happened fix time and date
//            if ((int)$arrayPartsTime[0] > 24) {
//
//                // get actual time on following day
//                $arrayPartsTime[0] = $arrayPartsTime[0] - 24;
//
//                $arrayPartsDate = explode("-", $beginDate);
//
//                // get separately month and year for check next
//                $month = $arrayPartsDate[1];
//                $year = $arrayPartsDate[0];
//
//                // get actual following day
//                $arrayPartsDate[2] = $arrayPartsDate[2] + 1;
//
//                // get count of days in month for check if we got the big number
//                $daysOfMonthForCheck = cal_days_in_month(CAL_GREGORIAN, $month, $year);
//
//                // if big -> change number of month on next
//                if ($arrayPartsDate[2] > $daysOfMonthForCheck) {
//
//                    // number next month
//                    $arrayPartsDate[2] = $arrayPartsDate[2] - $daysOfMonthForCheck;
//
//                    $arrayPartsDate[1] = $arrayPartsDate[1] + 1;
//
//                    // if very big got number of month
//                    if ($arrayPartsDate[1] > 12) {
//
//                        $arrayPartsDate[1] = $arrayPartsDate[1] - 12;
//
//                        // refresh year
//                        $arrayPartsDate[0] = $arrayPartsDate[0] + 1;
//                    }
//                }
//
//                $beginDate = implode('-', $arrayPartsDate);
//
//            }
//
//            // if need reduce time
//        } else {
//            // if got number with minus change him on plus
//            $fixTime = -($fixTime);
//
//            // if we try take away from number which less than the deductible
//            if ($fixTime > $arrayPartsTime[0]) {
//
//                // learn the remainder of the subtraction for subtraction from the next day
//                $newFixTime = $fixTime - (int)$arrayPartsTime[0];
//
//                $arrayPartsTime[0] = 24 - $newFixTime;
//
//                $arrayPartsDate = explode("-", $beginDate);
//
//                // refresh day
//                $arrayPartsDate[2] = (int)$arrayPartsDate[2] - 1;
//
//                if ($arrayPartsDate[2] === 0) {
//
//                    $arrayPartsDate[1] = ($arrayPartsDate[1] - 1 === 0) ? 12 : $arrayPartsDate[1] - 1;
//
//                    $month = $arrayPartsDate[1];
//                    $year = $arrayPartsDate[0];
//
//                    $arrayPartsDate[2] = cal_days_in_month(CAL_GREGORIAN, $month, $year);
//
//                }
//
//                $beginDate = implode("-", $arrayPartsDate);
//
//            } else {
//                $arrayPartsTime[0] = $arrayPartsTime[0] - $fixTime;
//            }
//        }
//
//        $arrayPartsTime[0] = ((int)$arrayPartsTime[0] < 10) ? "0" . $arrayPartsTime[0] : $arrayPartsTime[0];
//
//        $beginTime = implode(":", $arrayPartsTime);
//
//        return $beginDate . " " . $beginTime;
//
//    }

    protected function getBeginDate($arrayDataDate)
    {
        return '';
    }

    protected function getBeginTime($arrayDataDate)
    {
        return '';
    }

    protected function getTypeSport($obj)
    {
        $typeSport['type_sport'] = $this->upperCamelCase(3);
        return $typeSport;
    }

    protected function upperCamelCase($index)
    {
        return ucwords(explode('/', $this->urlOnEvent)[$index]);
    }

    protected function getCountry($html)
    {
        $country['name'] = $this->upperCamelCase(4);
        $resultArray['country'] = $country;
        return $resultArray;
    }

    protected function getCountryCss($html)
    {
        return '';
    }

    protected function getCountryName($html)
    {
        return '';
    }

    protected function getChampionship($obj)
    {
        $result = [];
        $result['name_tournament'] = $this->getChampionshipName($obj);
        return $result;
    }

    protected function getChampionshipName($obj)
    {
        $name = '';
        foreach ($obj->data->parents as $key => $childObj) {
            $name = trim(str_replace('1.', '', $this->deleteYears($childObj->name)));
        }
        return $name;
    }

    protected function getChampionshipId($html)
    {
        return '';
    }

    protected function getNameEvent($obj)
    {
        $teams = [];
        foreach ($obj->data->participant as $key => $childObj) {
            $teams[] = $childObj->name;
        }
        $nameEvent['name'] = $teams[1] . ' vs ' . $teams[0];
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

    protected function modifiedUrlOnEvent($urlOnEvent)
    {
        $arrayPartsUrl = explode('/', $urlOnEvent);
        $sport = $arrayPartsUrl[3];
        $country = $arrayPartsUrl[4];
        $tournament = $arrayPartsUrl[5];
        $match = $arrayPartsUrl[6];

        $newUrl = 'https://bbfeapi.betbrain.com/eventOverview/' . $sport . '/' . $country
            . '/' . $tournament . '/' . $match . '?requestId=9&wsTrack=' . $this->hash
            . '&registeringForUpdates=true&domain=www.betbrain.com&method=get';

        return $newUrl;
    }

    protected function putInCountryCountry2($event)
    {
        $country = $event["country"];
        $idCountry = $this->dbHelper->query("SELECT id FROM country4 WHERE `name`=?", $country)[0]['id'];
        $this->dbHelper->query("UPDATE country_country2 SET `id4`=? WHERE `name`=?", $idCountry, $country);
    }
}