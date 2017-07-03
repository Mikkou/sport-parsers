<?php

namespace parsersPicksgrail\helpers;

use DbSimple_Generic;

class DBHelper
{
    protected static $dbInstance;

    protected static $table;

    public static function getInstance($table)
    {
        self::$table = $table;

        if (self::$dbInstance === null) {

            self::$dbInstance = DbSimple_Generic::connect('mysql://' . USER . ':' . PASSWORD . '@' . HOST . '/' . NAME_BD . '');

        }

        return self::$dbInstance;
    }
}

