<?php

namespace parsersPicksgrail\helpers;

use DbSimple_Generic;

class DBHelper
{

    protected static $db;

    protected static $table;

    public static function getInstance($table)
    {
        self::$table = $table;

        if (self::$db === null) {
            self::$db = DbSimple_Generic::connect('mysql://' . USER . ':' . PASSWORD . '@' . HOST . '/' . NAME_BD . '');
        }

        return self::$db;

    }

    /*
    public function putBookmaker($id, $name)
    {
        $this->
    }
    */
}

