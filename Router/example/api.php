<?php

require_once("../Router.php");
require_once("../RouterException.php");

Router::Root(1, function() { print('sex'); });

Router::Act();