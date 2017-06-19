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

    //TODO оптимизировать этот конструктор
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

            $start = microtime(true);

            //получение ссылок на все события
            $arrayUrls = $this->getUrlsOnEvents($this->urlOfCategory, $forWhatDay);

            //dump($arrayUrls);

            echo "Parsing urls from 1 page of category was " . (microtime(true) - $start) . " sec.\n";

            $start = microtime(true);

            //парсим содержимое событий
            $events = $this->getDataOfEvents($arrayUrls);

            echo "Parsing event from 1 page of category was " . (microtime(true) - $start) . " sec.\n";

            //dump($events);

        }

        die;

    }

    public function getDataOfEvents($arrayUrls)
    {
        $resultArrayWithAllDataEvents = [];

        //распаршивание каждого события
        foreach($arrayUrls as $parseUrl) {

            $start = microtime(true);

            echo "Begin parse objects... \n";

            //не загружается страница
            $parseUrl = 'http://dev.bmbets.com/football/sweden/division-5/ifk-&#246;sterbymo-v-horn-hycklinge-if-1976803/';
            //сделать преобразование в html сущность
            //$parseUrl = 'http://dev.bmbets.com/football/sweden/division-5/ifk-&#246;sterbymo-v-horn-hycklinge-if-1976803/';
            //рынки
            //$parseUrl = 'http://dev.bmbets.com/football/korea-republic/k-league/ulsan-hyundai-v-jeju-united-1975696/';
            //home-no-bet проверить
            //$parseUrl = 'http://dev.bmbets.com/football/china-pr/fa-cup/shanghai-shenhua-v-beijing-guoan-1976760/#!/goal-line';
            //рынки и букмейкеры
            //$parseUrl = 'http://dev.bmbets.com/football/kazakhstan/kazakhstan-cup/kairat-almaty-v-ordabasy-1972615/';

            //нет ставок
            //http://prntscr.com/flrbv5
            //$parseUrl = 'http://dev.bmbets.com/football/sweden/division-2-norra-svealand/eskilstuna-city-v-sundbybergs-ik-1968721/';
            //$parseUrl = 'http://dev.bmbets.com/football/sweden/division-2-norrland/hudiksvall-v-harnosand-1977495/';

            $w = chr(246);

            dump("f" . $w . "s");

            dump(chr(246));

            dump(htmlspecialchars($parseUrl));
            die;

            //поулчение html страницы события
            $html = $this->getHtmlContentFromUrl($parseUrl);

            //выносим ссылку на события для использования в производных классах
            $this->urlOnEvent = $parseUrl;

            //ссылка на событие
            $url['url'] = $parseUrl;

            //получение даты начала события
            $beginDate = $this->getBeginDate($html);

            //получение времени начала события
            $beginTime = $this->getBeginTime($html);

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
            $arrayMergesData = array_merge($url, $beginDate, $beginTime, $typeSport, $country, $championship,
                $nameEvent, $markets, $bookmakers);

            dump($arrayMergesData);

            $resultArrayWithAllDataEvents[] = $arrayMergesData;

            echo "Parsing one event was " . (microtime(true) - $start) . " sec.\n";
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
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 25);
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);

            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            echo "Response from server - " . $httpCode . ".\n";

            //формирование ответа
            if ($httpCode === 301 || $httpCode === 302) {

                echo "A redirect has occurred!\n";

                return false;

            } elseif ($httpCode === 404) {

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

    abstract protected function getUrlsOnEvents($url, $forWhatDay);
    abstract protected function getCookies();
    abstract protected function getHeaders();
    abstract protected function getBeginDate($html);
    abstract protected function getBeginTime($html);
    abstract protected function getTypeSport($html);
    abstract protected function getCountry($html);
    abstract protected function getChampionship($html);
    abstract protected function getNameEvent($html);
    abstract protected function getMarkets($html);
    abstract protected function getBookmakers($html);

}