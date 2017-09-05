<?php

namespace parsersPicksgrail\boards\betbraincom;

use parsersPicksgrail\Parser;

class BetbrainComParser extends Parser
{
    protected $session_id = '';
    protected $wsTrack = '';
    protected $sp = null;
    protected $websocker_url = null;
    protected $user_agent_name = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:49.0) Gecko/20100101 Firefox/49.0';
    protected $bootstrap = '';

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
            'user-agent:Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36',
        ];

        return $headers;
    }

    public function getUrlsOnEvents($typeSport, $forWhatDay)
    {
        // Заходим на страницу матчей для обновления cookie записей
        $this->get_web('https://www.betbrain.com/next-matches/');
        // Парсим уникальный номер посетителя, для дальнейшей работы с сайтом
        $this->get_wsTrack();
        //Запрашиваем данные для сокета (сокет необхоим на всем протяжении работы с сайтом)
        $this->loop_get('https://bbfeapi.betbrain.com/websocket/info?t=1477986468092');
        // Открываем веб сокет
        if (!$this->websocket_open('https://bbfeapi.betbrain.com/websocket/249/' . $this->wsTrack . '_1/websocket')) {
            die("Unable to connect to server " . $this->websocker_url);
        };
        // Обновляем сессионный id из сокета
        $this->websocket_update_session_id();

        // ***************** предварительная подготовка скрипта для работы сайта закочена, теперь можно отправлять запросы на сайт. **********************

        $json = $this->loop_get('https://bbfeapi.betbrain.com/nextMatches?requestId=5&wsTrack=' . $this->wsTrack .
            '&pageIndexStart=1&pageIndexEnd=3000&domain=www.betbrain.com&isAdvancedFilters=false&method=get');
        $matches = json_decode($json);

        dump($matches);
        die;

        $bootstrap = $this->loop_get('https://bbfeapi.betbrain.com/bootstrap?requestId=5&wsTrack=' . $this->wsTrack . '&entities=BettingType&entities=Currency&entities=EventPart&entities=Location&entities=Provider&entities=Sport&registeringForUpdates=true&domain=www.betbrain.com&method=get');
        $this->bootstrap = json_decode($bootstrap);

        $resultEvents = [];
        foreach ($matches->data->events as $event) {
            // получение ссылки на событие
            $url = 'https://www.' . $this->domain . '/' . $event->urlSportName . '/' . $event->urlLocationName . '/' .
                $event->urlTournamentName . '/' . $event->urlMatchName . '/#/' . $event->urlBetTypeName . '/' .
                $event->urlEventPartName . '/';
            $resultEvents[] = $url;
        }

        return $resultEvents;
    }

    protected function websocket_update_session_id()
    {
        $key = base64_encode(uniqid());
        $header = "GET $this->websocker_url HTTP/1.1\r\n"
            . "Host: bbfeapi.betbrain.com\r\n"
            . "Connection: Upgrade\r\n"
            . "Pragma: no-cache\r\n"
            . "Cache-Control: no-cache\r\n"
            . "Upgrade: websocket\r\n"
            . "Origin: https://www.betbrain.com\r\n"
            . "Sec-WebSocket-Version: 13\r\n"
            . "User-Agent: " . $this->user_agent_name . "\r\n"
            . "Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4\r\n"
            . "Sec-WebSocket-Key: $key\r\n"
            . "Sec-WebSocket-Extensions: permessage-deflate; client_max_window_bits\r\n\r\n";

        fwrite($this->sp, $header);
        stream_set_timeout($this->sp, 5);
        $reaponse_header = fread($this->sp, 1024);

        if (!strpos($reaponse_header, " 101 ")
            || !strpos($reaponse_header, 'Sec-WebSocket-Accept: ')
        ) {
            die("Server did not accept to upgrade connection to websocket\n"
                . $reaponse_header);
        }

        $re = '/JSESSIONID=(.+?);/';
        preg_match_all($re, $reaponse_header, $matches);

        if (count($matches) < 1) {
            die("websocket session not found!\n");
        }

        if (isset($matches[1][0])) {
            $this->session_id = $matches[1][0];
        } else {
            $this->session_id = 0;
        }

        $this->set_bbfeapi_session($this->session_id);
    }

    protected function set_bbfeapi_session($session_id)
    {

        $new_file_content = [];
        $cookies = $this->getCookies();
        $file_content = file($cookies);

        if (count($file_content) == 0) return;

        foreach ($file_content as $file_line) {

            if (strpos($file_line, "#HttpOnly_bbfeapi.betbrain.com") === false) {
                $new_file_content[] = $file_line;
            }

        }

        $new_file_content[] = "#HttpOnly_bbfeapi.betbrain.com\tFALSE\t/\tTRUE\t0\tJSESSIONID\t$session_id\n";

        file_put_contents($cookies, $new_file_content);

    }

    protected function websocket_open($url)
    {
        $this->websocker_url = $url;
        $query = parse_url($url);
        $this->sp = stream_socket_client('ssl://' . $query['host'] . ':443', $errno, $errstr, 5);

        if (!$this->sp) return false;
        return true;
    }

    protected function loop_get($url)
    {
        for ($i = 1; $i <= 5; $i++) {
            $html = $this->get_web($url);
            if ($html && stripos($html, 'error') === false) {
                return $html;
            }
            sleep(1);
            fclose($this->sp);
            $this->websocket_open('https://bbfeapi.betbrain.com/websocket/249/' . $this->wsTrack . '_1/websocket');
            $this->websocket_update_session_id();
        }
        return false;
    }

    protected function get_wsTrack()
    {
        $html = $this->loop_get('https://bbfeapi.betbrain.com/httphs?method=get');
        if (!$html) {
            die("error get wsTrack\n" . $GLOBALS['curl_last_error']);
        }
        $this->wsTrack = json_decode($html)->wsTrack;
    }

    protected function get_web($url, $post_fields = false, $headers = false)
    {
        if ($curl = curl_init()) {

            $cookies = $this->getCookies();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl, CURLOPT_USERAGENT, $this->user_agent_name);

            if ($headers) {
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            }

            if ($post_fields) {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
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

    protected function modifiedUrlOnEvent($urlOnEvent)
    {
        return '';
    }

    protected function getTime($jObj)
    {

        $this->urlOnEvent = 'https://www.betbrain.com/football/iceland/urvalsdeild/fh-hafnarfjordur-v-umf-grindavik/#/both-teams-to-score/ordinary-time/';

        dump('https://www.betbrain.com/football/iceland/urvalsdeild/fh-hafnarfjordur-v-umf-grindavik/#/both-teams-to-score/ordinary-time/');

        $arrayPartsOfUrl = explode('/', $this->urlOnEvent);
        $sport = $arrayPartsOfUrl[3];
        $country = $arrayPartsOfUrl[4];
        $tournament = $arrayPartsOfUrl[5];
        $nameEvent = $arrayPartsOfUrl[6];
        $url = 'https://bbfeapi.betbrain.com/eventOverview/' . $sport . '/' . $country . '/' . $tournament .
            '/' . $nameEvent . '?requestId=16&wsTrack=' . $this->wsTrack .
            '&registeringForUpdates=true&domain=www.betbrain.com&method=get';

        $json = $this->loop_get($url);
        $event = json_decode($json);

        foreach ($event->data->event as $eObj) {
            $time = $eObj->startTime;
        }

        foreach ($event->data->participant as $eObj) {
            $partsNames[] = $eObj->name;
        }


        $event_url = $sport . '/' . $country . '/' . $tournament . '/' . $nameEvent;

        $event_overview = $this->loop_get('https://bbfeapi.betbrain.com/eventOverview/' . $event_url . '?requestId=6&wsTrack=' . $this->wsTrack . '&registeringForUpdates=true&domain=www.betbrain.com&method=get');
        $event_overview = json_decode($event_overview);

        $type_event_part = $this->loop_get('https://bbfeapi.betbrain.com/betTypeEventPart/' . $event_url . '?requestId=7&wsTrack=' . $this->wsTrack . '&registeringForUpdates=true&domain=www.betbrain.com&includeGroups=false&method=get');
        $type_event_part = json_decode($type_event_part);

        $match_info = [];
        foreach ($event_overview->data->parents as $item) {
            $match_info = $item;
        }

        $sport_name = $this->bootstrap->data->entities->sport->{$match_info->sportId}->name;
        $location_name = $this->bootstrap->data->entities->location->{$match_info->venueId}->name;
        $league_name = $match_info->name;

        $match_markets = [];
        if (count($type_event_part->data[0]->defaultBetTypeEventParts->allAvailableBettingTypes) > 0) {

            foreach ($type_event_part->data[0]->defaultBetTypeEventParts->allAvailableBettingTypes as $available_betting_types_item) {

                $match_markets[] = [
                    'name' => $this->bootstrap->data->entities->bettingType->{$available_betting_types_item->bettingType->id}->name,
                    'betting_type_name_url_fragment' => $available_betting_types_item->bettingType->name,
                    'part_name_url_fragment' => $available_betting_types_item->defaultEventPart->name,
                    'event_parts' => (array)$available_betting_types_item->availableEventPartIdToEventPartName
                ];
            }
        } else {
            $tst = 1;
            $match_markets[] = [
                'name' => $this->bootstrap->data->entities->bettingType->{$type_event_part->data[0]->defaultBetTypeEventParts->defaultBettingType->id}->name,
                'betting_type_name_url_fragment' => $type_event_part->data[0]->defaultBetTypeEventParts->defaultBettingType->name,
                'part_name_url_fragment' => $type_event_part->data[0]->defaultBetTypeEventParts->defaultEventPart->name
            ];
        }

        $resultArrayMarkets = [];



        foreach ($match_markets as $item) {
            $array['market_name'] = $item['name'];
            $array['market_id'] = NULL;
            if (count($item['event_parts']) > 1) {
                foreach ($item['event_parts'] as $item_event_part_key => $item_event_part_value) {
                    $name = $this->bootstrap->data->entities->eventPart->{$item_event_part_key}->name;
                    // останавливаем выборку, если пошел по второму кругу
//                    if (array_key_exists(0, $array['time_outs'])) {
//                        if ($array['time_outs'][0]['time_out_name'] === $name) {
//                            break;
//                        }
//                    }
                    // комплектуем данные в массив
                    if (!in_array($this->bootstrap->data->entities->eventPart->{$item_event_part_key}->name, $array)) {
                        $arrayPeriods = [];
                        $arrayPeriods['time_out_name'] = $name;
                        $arrayPeriods['time_out_id'] = NULL;
                        $array['time_outs'][] = $arrayPeriods;
                    }
                }
            }
            if (!array_key_exists('time_outs', $array)) {
                $array['time_outs'] = [];
            }
            $resultArrayMarkets[] = $array;
        }


        dump($resultArrayMarkets);
        die;

        dump($event);

        $resultArray["date_event"] = $time;
        $resultArray["type_sport"] = $sport_name;
        $resultArray["country"] = $location_name;
        $resultArray["name_tournament"] = $league_name;
        $resultArray["name"] = $partsNames[1] . ' vs ' . $partsNames[0];

        dump($resultArray);
        die;

        $resultArray["markets"] = $resultArrayMarkets;
        $resultArray["bookmakers"] = $arrayBookmakers;
        die;
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
        $this->dbHelper->query("UPDATE sport_sport2 SET `id3`=" . $idSport . ", `enable`=1 WHERE `name`=" . "'" . $event["type_sport"] . "'");
    }

    protected function putInCountryCountry2($event)
    {
        $country = $event["country"];
        $idCountry = $this->dbHelper->query("SELECT id FROM country4 WHERE `name`=?", $country)[0]['id'];
        $this->dbHelper->query("UPDATE country_country2 SET `id4`=? WHERE `name`=?", $idCountry, $country);
    }

    protected function putInBookmakerBookmaker2($event)
    {
        return '';
    }
}