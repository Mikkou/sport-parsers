<?php

namespace parsersPicksgrail;

use simple_html_dom;
use parsers\Helpers\proxyHelper;

abstract class Parser
{
    protected $urlsOfCategory;

    protected $domain;

    protected $days;

    protected $simpleDom;

    protected $config;

    function __construct($urlOfCategory, $domain, $days, $config)
    {
        $this->urlsOfCategory = $urlOfCategory;

        $this->domain = $domain;

        $this->days = $days;

        $this->config = $config;

        $this->simpleDom = new simple_html_dom();

        $this->proxyHelper = new proxyHelper($config, $this->domain);

    }

    public function start() {

        //цикл для кол-ва дней, за которое нужно распарсить
        for ($i = 1; $i <= $this->days; $i++) {

            //получение ссылок на все события
            $urls = $this->getUrlsOnEvents($this->urlsOfCategory);

            dump(1);
            die;

        }

    }

    /*
    public function getHtmlContentFromUrl($parseUrl)
    {

        $cookies = $this->getCookies();
        $headers = $this->getHeaders();

        sleep(1);

        if (TEST_MOD === 0 && !empty($this->proxyHelper)) {

            return $this->proxyHelper->getHtmlContentFromUrlWithProxy($parseUrl, $cookies, $headers, $this->domen);

        } else {

            $ch = curl_init($parseUrl);

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
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

                echo "A redirect has occurred!\n";

                return false;

            } elseif ($httpCode === 404) {

                return false;

            } else {

                //проверка кодировки и последующий возврат html
                return $this->checkHelper->checkEncoding($html, $this->domen);

            }

        }

    }
    */



    public function getHtmlObject($html, $selector) {

        $this->simpleDom->load($html);

        $object = $this->simpleDom->find($selector);

        return $object;

    }

    abstract protected function getUrlsOnEvents($url);
    abstract protected function getCookies();
    abstract protected function getHeaders();

}