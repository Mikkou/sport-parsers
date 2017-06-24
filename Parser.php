<?php

namespace parsersPicksgrail;

use simple_html_dom;
use parsersPicksgrail\helpers\ProxyHelper;
use parsersPicksgrail\helpers\DBHelper;

abstract class Parser
{
    protected $urlOfCategory;

    protected $domain;

    protected $days;

    protected $simpleDom;

    protected $config;

    protected $urlOnEvent;

    function __construct($urlOfCategory, $domain, $days, $config)
    {
        $this->urlOfCategory = $urlOfCategory;

        $this->domain = $domain;

        $this->days = $days;

        $this->config = $config;

        $this->simpleDom = new simple_html_dom();

        $this->proxyHelper = new ProxyHelper($config, $this->domain);

        $dbHelper = new DBHelper;
        $this->dbHelper = $dbHelper::getInstance($domain);

    }

    public function start() {

        //цикл для кол-ва дней, за которое нужно распарсить
        for ($forWhatDay = 1; $forWhatDay <= $this->days; $forWhatDay++) {

            /*
             * Сначала соберем все событие в кучу с одной страницы
             * и после начнем раскидывать их данные по таблицам в бд
             * */

            $start = microtime(true);

            //получение ссылок на все события
            $arrayUrls = $this->getUrlsOnEvents($this->urlOfCategory, $forWhatDay);

            echo "Parsing urls from 1 page of category was " . (microtime(true) - $start) . " sec.\n";

            $start = microtime(true);

            //парсим содержимое событий
            $events = $this->getDataOfEvents($arrayUrls);

            echo "Parsing event from 1 page of category was " . (microtime(true) - $start) . " sec.\n";

            //ложим все данные в базу данных
            $this->putEventsInDataBase($events);
        }
    }

    public function getDataOfEvents($arrayUrls)
    {
        $resultArrayWithAllDataEvents = [];

        //распаршивание каждого события
        foreach($arrayUrls as $parseUrl) {

            $start = microtime(true);

            echo "Begin parse objects... \n";

            //поулчение html страницы события
            $html = $this->getHtmlContentFromUrl($parseUrl);

            //выносим ссылку на события для использования в производных классах
            $this->urlOnEvent = $parseUrl;

            //ссылка на событие
            $url['link'] = $parseUrl;

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

            echo "Parsing one event was " . (microtime(true) - $start) . " sec.\n";

            $resultArrayWithAllDataEvents[] = $arrayMergesData;
        }

        return $resultArrayWithAllDataEvents;
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
            curl_setopt($ch, CURLOPT_HEADER, 1);
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

            echo "Response from server - " . $httpCode . ".\n";

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

    public function getHtmlObject($html, $selector) {

        $this->simpleDom->load($html);

        $object = $this->simpleDom->find($selector);

        return $object;

    }

    //TODO вынести в трейты эти все методы или что-нибудь с ними сделать
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
    abstract protected function putEventsInDataBase($events);
    abstract protected function putInBookmakers($events, $indexEvent);
    abstract protected function putInCountry($events, $indexEvent);
    abstract protected function putInEvent($events, $indexEvent);
    abstract protected function putInMarket($events, $indexEvent);
    abstract protected function putInSport($events, $indexEvent);
    abstract protected function putInSportCountry($events, $indexEvent);
    abstract protected function putInTournament($events, $indexEvent);

}