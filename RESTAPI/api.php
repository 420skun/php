<?php

header('Access-Control-Allow-Origin: *');
header('Content-Type: Application/json');

require_once('REST.php');

use DB\DB;
use DB\Config as DBConfig;
use REST\API;

$config = new DBConfig('127.0.0.1', 'crud', 'root', '');
DB::Config($config);

new API();