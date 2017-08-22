<?php

namespace parsersPicksgrail\boards\oddsportalcom;

use parsersPicksgrail\Parser;

class OddsportalComParser extends Parser
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
            'User-Agent:Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.109 Safari/537.36',
        ];

        return $headers;
    }

    public function getUrlsOnEvents($url, $forWhatDay)
    {
        // get part of need date
        $date = $this->getDateForUrl($forWhatDay);
        // join all together
        $date = $date["year"] . $date["month"] . $date["day"];
        // http request
        $html = $this->getHtmlContentFromUrl($url . $date . '/');
        // make http-header fro this site
        $headers = ['Referer: ' . $url . $date . '/'];
        // get id of sport
        $needTypeSport = explode('/', $url)[4];
        $sportId = $this->getSportId($needTypeSport);

        if (strpos($html, 'PageNextMatches') !== false) {
            $re = '/PageNextMatches\((.+?)\)/';
        } else if (strpos($html, 'PageEvent') !== false) {
            $re = '/PageEvent\((.+?)\)/';
        } else {
            die('error get page hash!');
        }

        preg_match($re, $html, $matches);

        if (!isset($matches[1])) {
            die('error get page hash!');
        }

        $hash = json_decode($matches[1]);

        $hash = urldecode($hash->xHashf->{$date});

        $html = $this->getWeb('http://fb.oddsportal.com/ajax-next-games/' . $sportId . '/0/2/' . $date . '/' . $hash . '.dat?_=1479303161702', false, $headers);

        $re = '/(<table.+?>.+?<\\\\\/table>)/';

        preg_match($re, $html, $matches);

        if (!isset($matches[1])) {
            die('error get matches table!');
        }

        $html = str_replace('\"', '"', $matches[1]);
        $html = str_replace('\/', '/', $html);

        $items = $this->getHtmlObject($html, '.table-participant > a');

        $resultArray = [];

        foreach ($items as $item) {

            $item_url = $item->getAttribute('href');

            if (strpos($item_url, 'javascript:void(0);') !== false) {
                continue;
            }
            if (strpos($item_url, 'inplay-odds') !== false) {
                continue;
            }

            $resultArray[] = 'http://www.oddsportal.com' . $item_url;
        }

        return $resultArray;
    }

    protected function get_hash($html)
    {

        if (strpos($html, 'PageNextMatches') !== false) {
            $re = '/PageNextMatches\((.+?)\)/';
        } else if (strpos($html, 'PageEvent') !== false) {
            $re = '/PageEvent\((.+?)\);var menu_open/';
        } else {
            die('error get page hash!');
        }

        preg_match($re, $html, $matches);

        if (!isset($matches[1])) {
            die('error get page hash!');
        }

        return json_decode($matches[1]);
    }

    protected function getSportId($needTypeSport)
    {
        $count = count($this->arraySpotsData);
        $sportId = '';
        for ($i = 0; $i < $count; $i++) {
            $typeSport = $this->arraySpotsData[$i]["url"];
            if ($typeSport === $needTypeSport) {
                $sportId = $this->arraySpotsData[$i]["id"];
            }
        }
        return $sportId;
    }

    protected function getWeb($url, $postFields = false, $headers = false)
    {
        if ($curl = curl_init()) {

            $cookies = $this->getCookies();

            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:49.0) Gecko/20100101 Firefox/49.0');

            if ($headers) {
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            }

            if ($postFields) {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
            }

            curl_setopt($curl, CURLOPT_COOKIEFILE, $cookies);
            curl_setopt($curl, CURLOPT_COOKIEJAR, $cookies);

            $out = curl_exec($curl);

            if (!$out) {
                $GLOBALS['curl_last_error'] = curl_error($curl);
                return false;
            }

            curl_close($curl);

            return $out;

        }
    }

    protected function getTime($html)
    {
        $item_url = str_replace('http://www.oddsportal.com', '', $this->urlOnEvent);

        $sport_id = $this->getSportId(explode('/', $item_url)[1]);;

        $html = $this->getWeb('http://www.oddsportal.com' . $item_url);

        if (!$html) {
            die('error get sport item!');
        }

        $breadcrumb = $this->getHtmlObject($html, '#col-content > p');

        $modifiedTime = str_replace(['date', 'datet', 't'], '', explode('-', $breadcrumb[0]->attr["class"])[0]);
        $time = (int) trim($modifiedTime);

        $breadcrumb = $this->getHtmlObject($html, '#breadcrumb > a');

        $sport_name = $breadcrumb[1]->plaintext;
        $sport_country = $breadcrumb[2]->plaintext;
        $sport_match_name = $breadcrumb[3]->plaintext;

        $hash = $this->get_hash($html);
        $hash_id = $hash->id;
        $hash_version_id = $hash->versionId;
        $hash_xhash = urldecode($hash->xhash);
        $bet_type = $this->get_default_bet_type($sport_id);
        $_scope_id = $this->get_scope_id($sport_id, $bet_type);

        $nameEvent = trim($hash->home) . ' - ' . trim($hash->away);

        $headers = array(
            'Referer: http://www.oddsportal.com' . $item_url
        );

        $html = $this->getWeb("http://fb.oddsportal.com/feed/match/$hash_version_id-$sport_id-$hash_id-$bet_type-$_scope_id-$hash_xhash.dat?_=1479583158479", false, $headers);

        if (!$html) {
            die('error get match odds array!');
        }

        $re = '/globals\.jsonpCallback\(.+?, (.+?)\);/';
        preg_match($re, $html, $matches);

        if (!isset($matches[1])) {
            die('error parse match odds array!');
        }

        $match_odds = json_decode($matches[1]);

        $resultArrayMarkets = [];

        foreach ($match_odds->d->nav as $match_odd_id => $match_odd_value) {

            $arrayMarkets["market_name"] = $this->get_betting_name($match_odd_id)['name'];

            foreach ($match_odd_value as $match_scope_key => $match_scope_value){

                $arrayMarkets["time_outs"][] = $this->get_scope_name($match_scope_key);

            }

            $resultArrayMarkets[] = $arrayMarkets;
        }






        dump($resultArrayMarkets);
        die;

        $resultArray["date_event"] = $time;
        $resultArray["type_sport"] = $sport_name;
        $resultArray["country"] = $sport_country;
        $resultArray["name_tournament"] = $sport_match_name;
        $resultArray["name"] = $nameEvent;

        return $resultArray;
    }

    protected function get_betting_name($betting_id){

        $betting_names = array(
            "11" => array("name" => "Winner", "short-name" => "Winner", "position" => "0", "outright" => true),
            "1" => array ("name" => "1X2", "short-name" => "1X2", "position" => "1", "outright" => false),
            "3" => array ("name" => "Home/Away", "short-name" => "Home/Away", "position" => "2", "outright" => false),
            "5" => array ("name" => "Asian Handicap", "short-name" => "AH", "position" => "3", "outright" => false),
            "2" => array ("name" => "Over/Under", "short-name" => "O/U", "position" => "4", "outright" => false),
            "6" => array("name" => "Draw No Bet", "short-name" => "DNB", "position" => "5", "outright" => false),
            "12" => array ("name" => "European Handicap", "short-name" => "EH", "position" => "6", "outright" => false),
            "4" => array ("name" => "Double Chance", "short-name" => "DC", "position" => "7", "outright" => false),
            "7" => array ("name" => "To Qualify", "short-name" => "TQ", "position" => "8", "outright" => false),
            "8" => array ("name" => "Correct Score", "short-name" => "CS", "position" => "9", "outright" => false),
            "9" => array ("name" => "Half Time / Full Time", "short-name" => "HT/FT", "position" => "10", "outright" => false),
            "10" => array ("name" => "Odd or Even", "short-name" => "O/E", "position" => "11", "outright" => false),
            "13" => array ("name" => "Both Teams to Score", "short-name" => "BTS", "position" => "12", "outright" => false)
        );

        return $betting_names[$betting_id];
    }

    protected function get_scope_name($scope_id){
        $scope_names = array(
            "1" => "FT&nbsp;including&nbsp;OT",
            "2" => "Full&nbsp;Time",
            "3" => "1st&nbsp;Half",
            "4" => "2nd&nbsp;Half",
            "5" => "1st&nbsp;Period",
            "6" => "2nd&nbsp;Period",
            "7" => "3rd&nbsp;Period",
            "8" => "1Q",
            "9" => "2Q",
            "10" => "3Q",
            "11" => "4Q",
            "12" => "1st&nbsp;Set",
            "13" => "2nd&nbsp;Set",
            "14" => "3rd&nbsp;Set",
            "15" => "4th&nbsp;Set",
            "16" => "5th&nbsp;Set",
            "17" => "1st&nbsp;Inning",
            "18" => "2nd&nbsp;Inning",
            "19" => "3rd&nbsp;Inning",
            "20" => "4th&nbsp;Inning",
            "21" => "5th&nbsp;Inning",
            "22" => "6th&nbsp;Inning",
            "23" => "7th&nbsp;Inning",
            "24" => "8th&nbsp;Inning",
            "25" => "9th&nbsp;Inning",
            "26" => "Next&nbsp;Set",
            "27" => "Current&nbsp;Set",
            "28" => "Next&nbsp;Game",
            "29" => "Current&nbsp;Game"
        );

        return $scope_names[$scope_id];
    }

    protected function get_scope_id($sport_id, $bettingTypeId)
    {

        $sportBetTypeScopeId = array(
            "4" => array("3" => 1),
            "2" => array("1" => 2, "2" => 2, "3" => 2));
        $betTypeScopeId = array("7" => 1);
        $sportScopeId = array(
            "3" => 1,
            "5" => 1,
            "6" => 1,
            "13" => 1,
            "18" => 1);

        $scope = 2;

        if (isset($sportBetTypeScopeId[$sport_id]) && $sportBetTypeScopeId[$sport_id][$bettingTypeId]) {
            $scope = $sportBetTypeScopeId[$sport_id][$bettingTypeId];
        } else if (isset($betTypeScopeId[$bettingTypeId])) {
            $scope = $betTypeScopeId[$bettingTypeId];
        } else if (isset($sportScopeId[$sport_id])) {
            $scope = $sportScopeId[$sport_id];
        }

        return $scope;
    }

    protected function get_default_bet_type($sport_id)
    {

        $moneyLineSports = array(
            '6' => 0,
            '2' => 1,
            '3' => 2,
            '5' => 3,
            '12' => 4,
            '14' => 5,
            '15' => 6,
            '13' => 7,
            '17' => 8,
            '18' => 9,
            '21"' => 10,
            '28"' => 11,
            '36' => 12
        );

        return (isset($moneyLineSports[$sport_id])) ? 3 : 1;

    }

    /**
     * преобразование года, месяца, дня, часа в соответствии с заданным таймаутом
     * этот метод создан только из-за того, что не получается настроить куки для того, чтобы
     * сайт сам отдавал нужные все данные о дате и времени. Поэтому делаем преобразование сами
     * @param string $beginDate
     * @param string $beginTime
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
            $arrayPartsTime[0] = ((int)($arrayPartsTime[0] + $fixTime) === 24) ? "00" : $arrayPartsTime[0] + $fixTime;

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
        for ($i = 0; $i < $count; $i++) {

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
        $part2 = trim(str_replace(['2017/2018', '2017', '2016/2017', '2019', '2017/2018', '2018'], '', $part2));

        $object2 = $this->getHtmlObject($html, '.wrap-section__header__title > a');
        $dirtyPart3 = trim($object2[0]->plaintext);

        if (strpos($dirtyPart3, ', ') !== false) {
            $array = explode(', ', $dirtyPart3);
            $part3 = $array[1];

            // если перед двоеточием будет страна, то не добавляем ее в результат имени
            if (strpos($part1, '-') !== false) {
                $resultName = $part1 . ": " . $part2 . ", " . $part3;
            } else {
                $resultName = $part2 . ", " . $part3;
            }

        } else {

            // если перед двоеточием будет страна, то не добавляем ее в результат имени
            if (strpos($part1, '-') !== false) {
                $resultName = $part1 . ": " . $part2;
            } else {
                $resultName = $part2;
            }

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
        $putArray['id_country'] = $this->dbHelper->query("SELECT id FROM country3 WHERE name=(?s)", $event['country'])[0]["id"];

        //проверка на дубли
        $result = $this->dbHelper->query("SELECT * FROM sport_country3 WHERE link=(?s)", $partLink);

        if (!$result) {
            //записываем все в бд
            $this->dbHelper->query("INSERT INTO sport_country3 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
        } else {
            // если такая страна есть, то открываем ее для пользователей
            $this->dbHelper->query("UPDATE sport_country3 SET `hide`=0, `id_sport`=(?), `id_country`=(?) WHERE `link`=(?s)",
                $putArray['id_sport'], $putArray['id_country'], $partLink);
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
        $array["name"] = $event['country'];

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
        return '';
    }

    protected $arraySpotsData = [
        [
            'name' => 'Soccer',
            'url' => 'soccer',
            'id' => 1
        ],
        [
            'name' => 'Tennis',
            'url' => 'tennis',
            'id' => 2
        ],
        [
            'name' => 'Basketball',
            'url' => 'basketball',
            'id' => 3
        ],
        [
            'name' => 'Hockey',
            'url' => 'hockey',
            'id' => 4
        ],
        [
            'name' => 'Handball',
            'url' => 'handball',
            'id' => 7
        ],
        [
            'name' => 'Baseball',
            'url' => 'baseball',
            'id' => 6
        ],
        [
            'name' => 'American-football',
            'url' => 'american-football',
            'id' => 5
        ],
        [
            'name' => 'Rugby-union',
            'url' => 'rugby-union',
            'id' => 8
        ],
        [
            'name' => 'Volleyball',
            'url' => 'volleyball',
            'id' => 12
        ],
        [
            'name' => 'Floorball',
            'url' => 'floorball',
            'id' => 9
        ],
        [
            'name' => 'Bandy',
            'url' => 'bandy',
            'id' => 10
        ],
        [
            'name' => 'Cricket',
            'url' => 'cricket',
            'id' => 13
        ],
        [
            'name' => 'Snooker',
            'url' => 'snooker',
            'id' => 15
        ],
        [
            'name' => 'Darts',
            'url' => 'darts',
            'id' => 14
        ],
        [
            'name' => 'Badminton',
            'url' => 'badminton',
            'id' => 21
        ],
        [
            'name' => 'Water-polo',
            'url' => 'water-polo',
            'id' => 22
        ],
        [
            'name' => 'Esports',
            'url' => 'esports',
            'id' => 36
        ],
    ];
}