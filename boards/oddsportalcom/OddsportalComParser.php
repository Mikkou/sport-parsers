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
        echo "\n" . $url . $date . '/';
        // http request
        $html = $this->getHtmlContentFromUrl($url . $date . '/');
        // make http-header fro this site
        $headers = ['Referer: ' . $url . $date . '/'];
        // get id of sport
        $needTypeSport = explode('/', $url)[4];
        $sportId = $this->getSportId($needTypeSport);

        $re = '';
        if (strpos($html, 'PageNextMatches') !== false) {
            $re = '/PageNextMatches\((.+?)\)/';
        } else if (strpos($html, 'PageEvent') !== false) {
            $re = '/PageEvent\((.+?)\)/';
        }

        preg_match($re, $html, $matches);

        $hash = json_decode($matches[1]);

        $hash = urldecode($hash->xHashf->{$date});

        $html = $this->getWeb('http://fb.oddsportal.com/ajax-next-games/' . $sportId . '/0/2/' . $date . '/' . $hash . '.dat?_=1479303161702', false, $headers);

        $re = '/(<table.+?>.+?<\\\\\/table>)/';

        preg_match($re, $html, $matches);

        if (!$matches) {
            return [];
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
        $re = '';
        if (strpos($html, 'PageNextMatches') !== false) {
            $re = '/PageNextMatches\((.+?)\)/';
        } else if (strpos($html, 'PageEvent') !== false) {
            $re = '/PageEvent\((.+?)\);var menu_open/';
        }

        preg_match($re, $html, $matches);

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

        $htmlOdd = $this->getWeb('http://www.oddsportal.com' . $item_url);

        $breadcrumb = $this->getHtmlObject($htmlOdd, '#col-content > p');

        $modifiedTime = str_replace(['date', 'datet', 't'], '', explode('-', $breadcrumb[0]->attr["class"])[0]);
        $time = trim(date('Y-m-d H:i:s', $modifiedTime));

        $breadcrumb = $this->getHtmlObject($htmlOdd, '#breadcrumb > a');

        $sport_name = $breadcrumb[1]->plaintext;
        $sport_country = $breadcrumb[2]->plaintext;
        $sport_match_name = $breadcrumb[3]->plaintext;

        $hash = $this->get_hash($htmlOdd);
        $hash_id = $hash->id;
        $hash_version_id = $hash->versionId;
        $hash_xhash = urldecode($hash->xhash);
        $bet_type = $this->get_default_bet_type($sport_id);
        $_scope_id = $this->get_scope_id($sport_id, $bet_type);

        $nameEvent = trim($hash->home) . ' - ' . trim($hash->away);

        $headers = ['Referer: http://www.oddsportal.com' . $item_url];

        $html = $this->getWeb("http://fb.oddsportal.com/feed/match/$hash_version_id-$sport_id-$hash_id-$bet_type-$_scope_id-$hash_xhash.dat?_=1479583158479", false, $headers);

        $re = '/globals\.jsonpCallback\(.+?, (.+?)\);/';
        preg_match($re, $html, $matches);

        $match_odds = json_decode($matches[1]);
        $resultArrayMarkets = [];

        if (array_key_exists('nav', $match_odds->d)) {
            foreach ($match_odds->d->nav as $match_odd_id => $match_odd_value) {
                $arrayMarkets["market_name"] = $this->get_betting_name($match_odd_id)['name'];
                $arrayMarkets["market_id"] = $match_odd_id;
                $arrayMarkets["time_outs"] = [];
                foreach ($match_odd_value as $match_scope_key => $match_scope_value) {
                    $arrayTimeOut = [];
                    $timeOutName = $this->get_scope_name($match_scope_key);
                    if (!in_array($timeOutName, $arrayMarkets["time_outs"])) {
                        $arrayTimeOut["time_out_name"] = $timeOutName;
                        $arrayTimeOut["time_out_id"] = $match_scope_key;
                    }
                    $arrayMarkets["time_outs"][] = $arrayTimeOut;
                }
                $resultArrayMarkets[] = $arrayMarkets;
            }
        }

        /// >>>>> get all bookmakers
//        $item_url = $_GET['sport_item'];
//        $sport_id = (int)$_GET['sport_id'];
//        $bet_type = (int)$_GET['match_odd_id'];

//        $html = get_web('http://www.oddsportal.com' . $item_url);

        $hash = $this->get_hash($htmlOdd);
        $hash_id = $hash->id;
        $hash_version_id = $hash->versionId;
        $hash_xhash = urldecode($hash->xhash);

//        $_scope_id = 2;
//        if (isset($_GET['match_scope_id'])) {
//            $_scope_id = (int)$_GET['match_scope_id'];
//        } else {
//            $_scope_id = get_scope_id($sport_id, $bet_type);
//        }

//        $headers = array(
//            'Referer: http://www.oddsportal.com' . $item_url
//        );

        $html = $this->getWeb('http://www.oddsportal.com/res/x/bookies-161111135702-1479567712.js', false, $headers);

        $re = '/var bookmakersData=(.+?);/';

        preg_match($re, $html, $matches);

        $bookmakers_data = json_decode($matches[1]);

//        $html = $this->getWeb("http://fb.oddsportal.com/feed/match/$hash_version_id-$sport_id-$hash_id-$bet_type-$_scope_id-$hash_xhash.dat?_=1479583158479", false, $headers);
//
//        if (!$html) {
//            die('error get match odds array!');
//        }
//
//        $re = '/globals\.jsonpCallback\(.+?, (.+?)\);/';
//        preg_match($re, $html, $matches);
//
//        if (!isset($matches[1])) {
//            die('error parse match odds array!');
//        }
//
//        $match_odds = json_decode($matches[1]);

        $arrayBookmakers = [];
        foreach ($bookmakers_data as $id => $objectWithName) {
            $array["name"] = $objectWithName->WebName;
            $array["id_from_site"] = (int)$objectWithName->idProvider;
            $arrayBookmakers[] = $array;
        }
        /// <<<< eng getting all bookmakers

        $resultArray["date_event"] = $time;
        $resultArray["type_sport"] = $sport_name;
        $resultArray["country"] = $sport_country;
        $resultArray["name_tournament"] = $sport_match_name;
        $resultArray["name"] = $nameEvent;
        $resultArray["markets"] = $resultArrayMarkets;
        $resultArray["bookmakers"] = $arrayBookmakers;

        return $resultArray;
    }

    protected function get_betting_name($betting_id)
    {

        $betting_names = array(
            "11" => array("name" => "Winner", "short-name" => "Winner", "position" => "0", "outright" => true),
            "1" => array("name" => "1X2", "short-name" => "1X2", "position" => "1", "outright" => false),
            "3" => array("name" => "Home/Away", "short-name" => "Home/Away", "position" => "2", "outright" => false),
            "5" => array("name" => "Asian Handicap", "short-name" => "AH", "position" => "3", "outright" => false),
            "2" => array("name" => "Over/Under", "short-name" => "O/U", "position" => "4", "outright" => false),
            "6" => array("name" => "Draw No Bet", "short-name" => "DNB", "position" => "5", "outright" => false),
            "12" => array("name" => "European Handicap", "short-name" => "EH", "position" => "6", "outright" => false),
            "4" => array("name" => "Double Chance", "short-name" => "DC", "position" => "7", "outright" => false),
            "7" => array("name" => "To Qualify", "short-name" => "TQ", "position" => "8", "outright" => false),
            "8" => array("name" => "Correct Score", "short-name" => "CS", "position" => "9", "outright" => false),
            "9" => array("name" => "Half Time / Full Time", "short-name" => "HT/FT", "position" => "10", "outright" => false),
            "10" => array("name" => "Odd or Even", "short-name" => "O/E", "position" => "11", "outright" => false),
            "13" => array("name" => "Both Teams to Score", "short-name" => "BTS", "position" => "12", "outright" => false)
        );

        return $betting_names[$betting_id];
    }

    protected function get_scope_name($scope_id)
    {
        $scope_names = [
            "1" => "FT including OT",
            "2" => "Full Time",
            "3" => "1st Half",
            "4" => "2nd Half",
            "5" => "1st Period",
            "6" => "2nd Period",
            "7" => "3rd Period",
            "8" => "1Q",
            "9" => "2Q",
            "10" => "3Q",
            "11" => "4Q",
            "12" => "1st Set",
            "13" => "2nd Set",
            "14" => "3rd Set",
            "15" => "4th Set",
            "16" => "5th Set",
            "17" => "1st Inning",
            "18" => "2nd Inning",
            "19" => "3rd Inning",
            "20" => "4th& Inning",
            "21" => "5th Inning",
            "22" => "6th Inning",
            "23" => "7th Inning",
            "24" => "8th Inning",
            "25" => "9th Inning",
            "26" => "Next Set",
            "27" => "Current Set",
            "28" => "Next Game",
            "29" => "Current Game"
        ];

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

        if (isset($sportBetTypeScopeId[$sport_id]) && isset($sportBetTypeScopeId[$sport_id][$bettingTypeId])) {
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

    protected function getBeginDate($arrayDataDate)
    {
        return [];
    }

    protected function getBeginTime($arrayDataDate)
    {
        return [];
    }

    protected function getTypeSport($html)
    {
        return [];
    }

    protected function getCountry($html)
    {
        return [];
    }

    protected function getCountryCss($html)
    {
        return '';
    }

    protected function getCountryName($html)
    {
        return [];
    }

    protected function getChampionship($html)
    {
        return [];
    }

    protected function getChampionshipName($html)
    {
        return [];
    }

    protected function getChampionshipId($html)
    {
        return '';
    }

    protected function getNameEvent($html)
    {
        return [];
    }

    public function getMarkets($html)
    {
        return [];
    }

    public function getBookmakers($html)
    {
        return [];
    }

    protected function putInTournament($event)
    {
        //достаем имя турнира
        $putArray["name"] = $event["name_tournament"];

        //получаем часть ссылки на турнир
        $arrayPartsLink = explode('/', $event["link"]);

        //собираем нужную часть ссылки
        $putArray["link"] = "/" . $arrayPartsLink[3] . "/" . $arrayPartsLink[4] . "/" . $arrayPartsLink[5] . "/";

        //получение айди-индекса с таблицы sport_county4 для столбца "id_sc"
        $link = "/" . $arrayPartsLink[3] . "/" . $arrayPartsLink[4] . "/";
        $arrayId = $this->dbHelper->query("SELECT id FROM sport_country4 WHERE link=(?s)", $link);
        $putArray["id_sc"] = (array_key_exists(0, $arrayId)) ? (int)$arrayId[0]["id"] : NULL;

        //проверка на дубли
        $result = $this->dbHelper->query("SELECT * FROM tournament4 WHERE link=(?s)", $putArray["link"]);

        if (!$result) {
            //записываем все в бд
            $this->dbHelper->query("INSERT INTO tournament4 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
        }
    }

    protected function putInSportCountry($event)
    {
        $arrayPartsLink = explode('/', $event["link"]);

        //собираем нужную часть ссылки
        $partLink = "/" . $arrayPartsLink[3] . "/" . $arrayPartsLink[4] . "/";

        $putArray['link'] = $partLink;
        $putArray['id_sport'] = $this->dbHelper->query("SELECT id FROM sport4 WHERE name=(?s)", $event['type_sport'])[0]["id"];
        $putArray['id_country'] = $this->dbHelper->query("SELECT id FROM country4 WHERE name=(?s)", $event['country'])[0]["id"];

        // check on dublicate
        $result = $this->dbHelper->query("SELECT * FROM sport_country4 WHERE link=(?s)", $partLink);

        if (!$result) {
            // write all in database
            $this->dbHelper->query("INSERT INTO sport_country4 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
        } else {
            // if that country have, then open her for users
            $this->dbHelper->query("UPDATE sport_country4 SET `hide`=0, `id_sport`=(?), `id_country`=(?) WHERE `link`=(?s)",
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
            $codeMarket = $event["markets"][0]["market_id"];
            $codePeriod = $event["markets"][0]["time_outs"][0]["time_out_id"];
            $arrayResult = $this->dbHelper->query("SELECT id FROM market4 WHERE code_market=(?s) AND code_period=(?s)",
                $codeMarket, $codePeriod);
            $putArray["id_market"] = $arrayResult[0]["id"];
        }

        //проверка на дубли
        $result = $this->dbHelper->query("SELECT * FROM sport4 WHERE name=(?s)", $putArray["name"]);

        if (!$result) {
            //записываем все в бд
            $this->dbHelper->query("INSERT INTO sport4 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
        } elseif (!empty($putArray["id_market"])) {
            $this->dbHelper->query("UPDATE sport4 SET `hide`=0, `id_market`=(?) WHERE `link`=(?s)", $putArray["id_market"], $putArray["link"]);
        } else {
            // return sport from hide
            $this->dbHelper->query("UPDATE sport4 SET `hide`=0 WHERE `link`=(?s)", $putArray["link"]);
        }
    }

    protected function putInMarket($event)
    {
        // get all markets of events
        $arrayMarkets = $event["markets"];

        //считаем их
        $countMarkets = count($arrayMarkets);

        // go in every market and get him data
        for ($i = 0; $i < $countMarkets; $i++) {

            //считаем кол-во таймаутов
            $countPeriods = count($arrayMarkets[$i]["time_outs"]);

            //проходимся по всем таймаутам
            for ($u = 0; $u < $countPeriods; $u++) {

                //формирование массива для записи в бд
                $putArray["code_market"] = $arrayMarkets[$i]["market_id"];
                $putArray["code_period"] = $arrayMarkets[$i]["time_outs"][$u]["time_out_id"];
                $putArray["name"] = $arrayMarkets[$i]["market_name"];
                $putArray["period"] = $arrayMarkets[$i]["time_outs"][$u]["time_out_name"];

                //проверка на дубли
                $result = $this->dbHelper->query("SELECT * FROM market4 WHERE code_market=(?s) AND code_period=(?s)",
                    $putArray["code_market"], $putArray["code_period"]);

                if (!$result) {
                    //записываем все в бд
                    $this->dbHelper->query("INSERT INTO market4 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
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

        //>>>получение ссылки на турнир и запрос его айди с таблицы tournament4
        //получаем часть ссылки на турнир
        $arrayPartsLink = explode('/', $event["link"]);
        //собираем нужную часть ссылки
        $link = "/" . $arrayPartsLink[3] . "/" . $arrayPartsLink[4] . "/" . $arrayPartsLink[5] . "/";
        //запрашиваем
        $arrayId = $this->dbHelper->query("SELECT id FROM tournament4 WHERE link=(?s)", $link);
        $putArray["id_tournament"] = $arrayId[0]["id"];
        //<<<

        //удаление старых событий
        $this->dbHelper->query("DELETE FROM event4 WHERE `link`=(?s)", $putArray["link"]);

        //добавление новых
        $this->dbHelper->query("INSERT INTO event4 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
    }

    protected function putInBookmakers($event)
    {
        $count = count($event['bookmakers']);
        for ($i = 0; $i < $count; $i++) {
            $array = $event['bookmakers'][$i];
            $idFromSite = $event['bookmakers'][$i]["id_from_site"];
            $nameBookmaker = $event['bookmakers'][$i]["name"];
            $result = $this->dbHelper->query("SELECT * FROM bookmaker4 WHERE id_from_site={$idFromSite}");
            if (!$result) {
                $this->dbHelper->query("INSERT INTO bookmaker4 (?#) VALUES (?a)", array_keys($array), array_values($array));
            } else {
                $this->dbHelper->query("UPDATE bookmaker4 SET id_from_site={$idFromSite} WHERE name={$nameBookmaker}");
            }
        }
    }

    protected function putInCountry($event)
    {
        $array["name"] = $event['country'];
        //проверка на дубли
        $result = $this->dbHelper->query("SELECT name FROM country4 WHERE name=(?s)", $array["name"]);
        if (!$result) {
            $this->dbHelper->query("INSERT INTO country4 (?#) VALUES (?a)", array_keys($array), array_values($array));
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
        // get id of event from table where they have
        $idEvent = $this->dbHelper->query("SELECT id FROM event4 WHERE link=" . "'" . $event["link"] . "'" . " ");

        //берем все рынки события
        $arrayMarkets = $event["markets"];

        //считаем их
        $countMarkets = count($arrayMarkets);

        //проходимся по всем рынкам
        for ($i = 0; $i < $countMarkets; $i++) {

            $nameMarket = $arrayMarkets[$i]["market_name"];

            $idMarket = $this->dbHelper->query("SELECT id FROM market4 WHERE name=" . "'" . $nameMarket . "'" . " ");

            //формирование массива для записи в бд
            $putArray["id_event"] = (int)$idEvent[0]["id"];
            $putArray["id_market"] = (int)$idMarket[0]["id"];

            //проверка на дубли
            $result = $this->dbHelper->query("SELECT * FROM event_market4 WHERE `id_event`=(?s) AND `id_market`=(?s)",
                $putArray["id_event"], $putArray["id_market"]);

            if (!$result) {
                //записываем все в бд
                $this->dbHelper->query("INSERT INTO event_market4 (?#) VALUES (?a)", array_keys($putArray), array_values($putArray));
            }
        }
    }

    protected function putInSportSport2($event)
    {
        //получаем айдишник вида спорта с таблицы, где они хранятся
        $idSport = $this->dbHelper->query("SELECT id FROM sport4 WHERE `name`=" . "'" . $event["type_sport"] . "'" . " ")[0]["id"];
        $this->dbHelper->query("UPDATE sport_sport2 SET `id4`=" . $idSport . " WHERE `name`=" . "'" . $event["type_sport"] . "'");
    }

    protected function modifiedUrlOnEvent($urlOnEvent)
    {
        return '';
    }

    protected $arraySpotsData = [
        [
            'name' => 'Football',
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
        [
            'name' => 'Rugby League',
            'url' => 'rugby-league',
            'id' => 19
        ],
        [
            'name' => 'Boxing',
            'url' => 'boxing',
            'id' => 16
        ],
        [
            'name' => 'MMA',
            'url' => 'mma',
            'id' => 28
        ],
        [
            'name' => 'Futsal',
            'url' => 'futsal',
            'id' => 11
        ],
        [
            'name' => 'Beach Volleyball',
            'url' => 'beach-volleyball',
            'id' => 17
        ],
        [
            'name' => 'Aussie Rules',
            'url' => 'aussie-rules',
            'id' => 18
        ],
        [
            'name' => 'Pesäpallo',
            'url' => 'pesapallo',
            'id' => 30
        ],
    ];

    protected function putInCountryCountry2($event)
    {
        $country = $event["country"];
        $idCountry = $this->dbHelper->query("SELECT id FROM country4 WHERE `name`=?", $country)[0]['id'];
        $this->dbHelper->query("UPDATE country_country2 SET `id4`=? WHERE `name`=?", $idCountry, $country);
    }
}