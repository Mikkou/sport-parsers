<?php

namespace parsersPicksgrail;

use simple_html_dom;

abstract class Parser
{
    protected $urlsOfCategory;

    protected $domain;

    protected $days;

    protected $simpleDom;

    function __construct($urlOfCategory, $domain, $days)
    {
        $this->urlsOfCategory = $urlOfCategory;

        $this->domain = $domain;

        $this->days = $days;

        $this->simpleDom = new simple_html_dom();

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

    public function getHtmlObject($html, $selector) {

        $this->simpleDom->load($html);

        $object = $this->simpleDom->find($selector);

        return $object;

    }

    abstract protected function getUrlsOnEvents();

}