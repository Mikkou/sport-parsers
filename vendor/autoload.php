<?php

// autoload.php @generated by Composer

require_once __DIR__ . '/composer/autoload_real.php';

require_once MAIN_DIR . '/Parser.php';

//helpers
require_once MAIN_DIR . '/helpers/ProxyHelper.php';
require_once MAIN_DIR . '/helpers/DBHelper.php';

//classes dask
require_once MAIN_DIR . '/boards/devbmbetscom/DevBmbetsComParser.php';
require_once MAIN_DIR . '/boards/betexplorercom/BetexplorerComParser.php';
require_once MAIN_DIR . '/boards/betbraincom/BetbrainComParser.php';
require_once MAIN_DIR . '/boards/oddsportalcom/OddsportalComParser.php';

//libraries
require_once MAIN_DIR . '/vendor/simplehtmldom/Simple_html_dom.php';
require_once MAIN_DIR . '/vendor/DbSimple-master/lib/config.php';
require_once MAIN_DIR . '/vendor/DbSimple-master/lib/DbSimple/Generic.php';
require_once MAIN_DIR . '/vendor/cloudflare-bypass/libraries/httpProxyClass.php';
require_once MAIN_DIR . '/vendor/cloudflare-bypass/libraries/cloudflareClass.php';

return ComposerAutoloaderInit2bda76166076047e03d78b342a421bab::getLoader();
