<?php

namespace parsersPicksgrail;

use simple_html_dom;
use parsersPicksgrail\helpers\ProxyHelper;
use parsersPicksgrail\helpers\DBHelper;

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

            echo "Parsing " . count($arrayUrls) . " urls for " . round((microtime(true) - $start), 2)
                . " sec.\n";

            // parsing and write in db events one by one
            foreach ($arrayUrls as $key => $url) {

                $dataOfEvent = $this->getDataOfEvent($url);

                // ложим все данные в базу данных
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

        //поулчение html страницы события
        $html = $this->getHtmlContentFromUrl($urlOnEvent);

        //выносим ссылку на события для использования в производных классах
        $this->urlOnEvent = $urlOnEvent;

        //ссылка на событие
        $url['link'] = $urlOnEvent;

        //получение времени
        $time = $this->getTime($html);

        //получение вида спорта
        $typeSport = $this->getTypeSport($html);

        //получение страны
        $country = $this->getCountry($html);

        //получение имени чемпионата и его айди
        $championship = $this->getChampionship($html);

        //получение имени события
        $nameEvent = $this->getNameEvent($html);

        //получение имен рынков с их разделениями по таймаутам
        $markets = $this->getMarkets($html);

        //получение букмейкерских контор
        $bookmakers = $this->getBookmakers($html);

        //объединение всех данных
        $arrayMergesData = array_merge($url, $time, $typeSport, $country, $championship, $nameEvent, $markets,
            $bookmakers);

        //проверка данных события
        $checkedArrayData = $this->checkEvent($html, $arrayMergesData);

        echo "parsed for " . round((microtime(true) - $start), 2) . " sec.\n";

        return $checkedArrayData;
    }

    public function getHtmlContentFromUrl($parseUrl)
    {
        $cookies = $this->getCookies();
        $headers = $this->getHeaders();

        sleep(1);

        if (TEST_MOD === 0 && !empty($this->proxyHelper)) {

            return $this->proxyHelper->getHtmlContentFromUrlWithProxy($parseUrl, $cookies, $headers, $this->domain);

        } else {

            $ch = curl_init($parseUrl);

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            $html = curl_exec($ch);
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

            } else {

                return $html;

            }
        }
    }

    public function putEventInDataBase($event)
    {
        //проверка: игнорировать событие для сохранения в бд или нет
        if (array_key_exists('ignore_event', $event)) { return ''; }

        //конторы
        $this->putInBookmakers($event);
        //страны
        $this->putInCountry($event);
        //турниры (должен быть перед putInEvent)
        $this->putInTournament($event);
        //само событие
        $this->putInEvent($event);
        //рынки
        $this->putInMarket($event);
        //данные вида спорта
        $this->putInSport($event);
        //
        $this->putInSportCountry($event);
        //сводная таблица событий и их рынков (метод должен быть после putInEvent)
        $this->putInEventMarkets($event);
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
}