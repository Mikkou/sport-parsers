<?php

namespace parsersPicksgrail;

use simple_html_dom;
use parsersPicksgrail\helpers\ProxyHelper;
use parsersPicksgrail\helpers\DBHelper;

abstract class Parser
{
    protected $urlsOfCategory;

    protected $domain;

    protected $days;

    protected $simpleDom;

    protected $config;

    //TODO оптимизировать этот конструктор
    function __construct($urlOfCategory, $domain, $days, $config)
    {
        $this->urlsOfCategory = $urlOfCategory;

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

            //получение ссылок на все события
            $arrayUrls = $this->getUrlsOnEvents($this->urlsOfCategory, $forWhatDay);

            //парсим содержимое событий
            $events = $this->getDataOfEvents($arrayUrls);

        }

        dump(1);
        die;

    }

    public function getDataOfEvents($arrayUrls)
    {
        $resultArrayWithAllDataEvents = [];

        foreach($arrayUrls as $url) {

            $arrayDataForOneEvent = [];

            $html = $this->getHtmlContentFromUrl($url);



        }
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

}