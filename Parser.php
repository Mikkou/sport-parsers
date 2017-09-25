<?php

namespace parsersPicksgrail;

use simple_html_dom;
use parsersPicksgrail\helpers\ProxyHelper;

abstract class Parser
{
    protected $urlOfCategory;
    protected $domain;
    protected $simpleDom;
    protected $config;
    protected $urlOnEvent;
    protected $keysForOptions;
    protected $parsedAllEvents = 0;
    protected $dbHelper;

    function __construct($urlOfCategory, $domain, $config, $keysForOptions, $DBHelper)
    {
        $this->urlOfCategory = $urlOfCategory;
        $this->domain = $domain;
        $this->config = $config;
        $this->keysForOptions = $keysForOptions;
        $this->simpleDom = new simple_html_dom();
        $this->proxyHelper = new ProxyHelper($config, $this->domain);
        $this->dbHelper = $DBHelper;
    }

    public function start()
    {
        $days = $this->getDays();
        //цикл для кол-ва дней, за которое нужно распарсить
        for ($forWhatDay = 1; $forWhatDay <= $days; $forWhatDay++) {

            $start = microtime(true);

            $arrayUrls = $this->getUrlsOnEvents($this->urlOfCategory, $forWhatDay);

            if (empty($arrayUrls)) { continue; }

            echo "\nParsing " . count($arrayUrls) . " urls for " . round((microtime(true) - $start), 2)
                . " sec.\n";

            // parsing and write in db events one by one
            foreach ($arrayUrls as $key => $url) {
                $dataOfEvent = $this->getDataOfEvent($url);
                $this->putEventInDataBase($dataOfEvent);
            }

            echo "Parsed " . count($arrayUrls) . " objects \n";
        }
    }

    public function getDataOfEvent($urlOnEvent)
    {
        //подсчет кол-ва распаршенных событий
        echo ++$this->parsedAllEvents . " - ";
        $start = microtime(true);
        //выносим ссылку на события для использования в производных классах
        $this->urlOnEvent = $urlOnEvent;
        $urlOnEvent = $this->modifiedUrlOnEvent($urlOnEvent);
        $response = $this->getHtmlContentFromUrl($urlOnEvent);
        $url['link'] = $this->urlOnEvent;
        $time = $this->getTime($response);
        $typeSport = $this->getTypeSport($response);
        $country = $this->getCountry($response);
        //получение имени чемпионата и его айди
        $championship = $this->getChampionship($response);
        $nameEvent = $this->getNameEvent($response);
        //получение имен рынков с их разделениями по таймаутам
        $markets = $this->getMarkets($response);
        //получение букмейкерских контор
        $bookmakers = $this->getBookmakers($response);
        //объединение всех данных
        $arrayMergesData = array_merge($url, $time, $typeSport, $country, $championship, $nameEvent, $markets,
            $bookmakers);
        //проверка данных события
        $checkedArrayData = $this->checkEvent($response, $arrayMergesData);
        echo "parsed for " . round((microtime(true) - $start), 2) . " sec.\n";
        return $checkedArrayData;
    }

    public function getHtmlContentFromUrl($url, $headers = false)
    {
        if (empty($url)) {
            return '';
        }

        if (!$headers) {
            $headers = $this->getHeaders();
        }

        $cookies = $this->getCookies();

        sleep(1);
        $ch = curl_init($url);
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if ($this->domain === 'oddsportal.com' || $this->domain === 'betbrain.com') {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:49.0) Gecko/20100101 Firefox/49.0');
        }

        if ($this->domain !== 'betbrain.com') {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

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
            return $this->proxyHelper->getHtmlContentFromUrlWithProxy($url, $cookies, $headers, $this->domain);
        }
    }

    public function putEventInDataBase($event)
    {
        //проверка: игнорировать событие для сохранения в бд или нет
        if (array_key_exists('ignore_event', $event)) { return false; }
        //конторы
        $this->putInBookmakers($event);
        // слитие всех айдишников букмейкеров в одну таблицу
        $this->putInBookmakerBookmaker2($event);
        //страны
        $this->putInCountry($event);
        // должно быть обязательно перед putInSport and putInTournament
        $this->putInSportCountry($event);
        //турниры (должен быть перед putInEvent)
        $this->putInTournament($event);
        //само событие
        $this->putInEvent($event);
        //рынки
        $this->putInMarket($event);
        // data type of sport
        $this->putInSport($event);
        //сводная таблица событий и их рынков (метод должен быть после putInEvent)
        $this->putInEventMarkets($event);
        $this->putInSportSport2($event);
        $this->putInCountryCountry2($event);
    }

    public function getHtmlObject($html, $selector)
    {
        $this->simpleDom->load($html);
        $object = $this->simpleDom->find($selector);
        return $object;
    }

    public function getDays()
    {
        //получение количества дней
        $key = $this->dbHelper->query("SELECT `value` FROM options WHERE `key` = " . "'" . $this->keysForOptions["days"]
            . "'" . " ");
        return $key[0]["value"];
    }

    protected function getTimeZone()
    {
        //получение временной зоны
        $key = $this->dbHelper->query("SELECT `value` FROM options WHERE `key` = " . "'" . $this->keysForOptions["timeZone"]
            . "'" . " ");
        return $key[0]["value"];
    }


    /**
     * @param $forWhatDay - today, tomorrow and so on
     * @param $whatNeed - integer of day, month or year will return
     * @return int - day for parsing
     */
    protected function getDateForParse($forWhatDay, $whatNeed)
    {
        if ($whatNeed === "day") {
            return (int)date("d", strtotime("+" . ($forWhatDay - 1) . " day"));
        } elseif ($whatNeed === "month") {
            return (int)date("m", strtotime("+" . ($forWhatDay - 1) . " day"));
        } elseif ($whatNeed === "year") {
            return (int)date("Y", strtotime("+" . ($forWhatDay - 1) . " day"));
        }
    }

    /**
     * @param $string - with years for delete
     * @return string - without years
     */
    protected function deleteYears($string)
    {
        return trim(str_replace(['2017/2018', '2017', '2016/2017', '2019', '2017/2018', '2018'], '', $string));
    }

    /**
     * @param $forWhatDay - parsing. For example, if get 1 then today, if 2 - tomorrow, and so on.
     * @return array - with parts of need date
     * @param bool $separator - anyone separator example like '-'
     * @return mixed
     */
    protected function getFullDate($forWhatDay, $separator = false)
    {
        // get year in format "2017"
        $result["year"] = $this->getDateForParse($forWhatDay, "year");
        // get number of month like "7" and check (add 0)
        $result["month"] = $this->getDateForParse($forWhatDay, "month");
        $result["month"] = ((int)$result["month"] < 10) ? "0" . $result["month"] : $result["month"];
        // get number of day like "31" and check (add 0)
        $result["day"] = $this->getDateForParse($forWhatDay, "day");
        $result["day"] = ((int)$result["day"] < 10) ? "0" . $result["day"] : $result["day"];
        if ($separator) {
            $result['date'] = $result["year"] . $separator . $result["month"] . $separator . $result["day"];
        }
        return $result;
    }

    /**
     * преобразование года, месяца, дня, часа в соответствии с заданным таймаутом
     * @param string $beginDate
     * @param string  $beginTime
     * @param int  $defaultTimeZoneOnSite
     * @return string
     */
    protected function checkDateAndTime($beginDate, $beginTime, $defaultTimeZoneOnSite)
    {
        $needTime = $this->getTimeZone();

        if ($defaultTimeZoneOnSite < 0) {
            $fixTime = $needTime + ($defaultTimeZoneOnSite);
        } else {
            $fixTime = $needTime - $defaultTimeZoneOnSite;
        }

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

    abstract protected function getUrlsOnEvents($url, $forWhatDay);

    abstract protected function getCookies();

    abstract protected function getHeaders();

    abstract protected function getTime($html);

    abstract protected function getBeginDate($html);

    abstract protected function getBeginTime($html);

    abstract protected function getTypeSport($html);

    abstract protected function getCountry($html);

    abstract protected function getCountryCss($html);

    abstract protected function getCountryName($html);

    abstract protected function getChampionship($html);

    abstract protected function getNameEvent($html);

    abstract protected function getMarkets($html);

    abstract protected function getBookmakers($html);

    abstract protected function putInBookmakers($event);

    abstract protected function putInCountry($event);

    abstract protected function putInEvent($event);

    abstract protected function putInMarket($event);

    abstract protected function putInSport($event);

    abstract protected function putInSportCountry($event);

    abstract protected function putInTournament($event);

    abstract protected function checkEvent($html, $arrayMergesData);

    abstract protected function putInEventMarkets($event);

    abstract protected function putInSportSport2($event);

    abstract protected function putInCountryCountry2($event);

    abstract protected function modifiedUrlOnEvent($urlOnEvent);

    abstract protected function putInBookmakerBookmaker2($event);
}