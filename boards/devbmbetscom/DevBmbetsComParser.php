<?php

namespace parsersPicksgrail\boards\devbmbetscom;

use parsersPicksgrail\Parser;

class DevBmbetsComParser extends Parser
{
    function __construct($urlOfCategory, $domain, $days, $config)
    {
        parent::__construct($urlOfCategory, $domain, $days, $config);
    }

    public function getCookies()
    {
        $cleanDomain = str_replace('.', '', $this->domain);

        $path = getcwd() . "\boards\\" . $cleanDomain . "\cookies.txt";

        return $path;
    }

    public function getHeaders()
    {

        $headers = [
            'User-Agent:Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.104 Safari/537.36',
            'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Connection:keep-alive',
            'Accept-Language:ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4',
            'Cookie:__cfduid=da5ae61e755bd97072dccbe838ce141671497706408; cf_clearance=2da2140b16c9d673c6a284d035b947020ecf1142-1497716387-3600; Language=en-US; __RequestVerificationToken=KiyFzjh7e9BtchUvAiEGK955mnDdMONFYFfswjqDtpaAjDyLbDq4KdedLnO0Vxj49L5FvSJnULMMwXfFmLjGmI20tk9b9LZYLk9y5Y3-Irs1; gmt=3; _hjIncludedInSample=1; _ga=GA1.2.634684937.1497706431; _gid=GA1.2.1059422375.1497706431',
            'Host:dev.bmbets.com',
            'Upgrade-Insecure-Requests:1',
        ];

        return $headers;
    }

    public function getUrlsOnEvents($url, $forWhatDay)
    {

        $url = $this->createUrl($url, $forWhatDay);

        $html = $this->getHtmlContentFromUrl($url);

        //получаем объект из всех актуальных событий
        $object = $this->getHtmlObject($html, 'tr[class="main-table-row  "]');

        $countEvents = count($object);

        $arrayEventsUrls = [];

        //собираем все ссылки в единый массив
        for ($i = 0; $i < $countEvents; $i++) {

            $partOfUrl = $object[$i]->children[2]->children[0]->children[0]->attr['href'];

            //формируем полную ссылку
            $link = "http://" . $this->domain . $partOfUrl;

            $arrayEventsUrls[] = $link;

        }

        return $arrayEventsUrls;

    }

    protected function createUrl($url, $forWhatDay)
    {
        if ($forWhatDay === 1) {

            $url .= "/" . date('Ymd');

        } else {

            $url .= "/" . (date('Ymd') + ($forWhatDay - 1));

        }

        return $url;
    }

}