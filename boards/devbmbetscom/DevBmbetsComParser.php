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
            'Accept-Encoding:gzip, deflate',
            'Accept-Language:ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4',
            'Cookie:__cfduid=dd3f03a6d77004c3beb759178d6e81d021497702622; cf_clearance=3da4737af93a75e1bf587e1b3d43c18a1d29d2db-1497702626-3600; Language=en-US; IsWelcome=1; __RequestVerificationToken=TfuUX9YHwGAsV3DKGGURwE4tb2xtcX23Yn28d_tpFMU0RZs_wYo-m_y0N-u-fT5BJSOMNyGx1BuHBBzTLPW7j4iMyTsei_ApcwNs3DMoSIA1; gmt=3; _hjIncludedInSample=1; _ga=GA1.2.1649876942.1497702636; _gid=GA1.2.283928410.1497702636; _gat=1',
            'Host:dev.bmbets.com',
            'Upgrade-Insecure-Requests:1',
        ];

        return $headers;
    }

    public function getUrlsOnEvents($url)
    {

        $cookies = $this->getCookies();

        $headers = $this->getHeaders();

        $html = $this->proxyHelper->getHtmlContentFromUrlWithProxy($url, $cookies, $headers, $this->domain);

        echo $html;
        die;
    }

}