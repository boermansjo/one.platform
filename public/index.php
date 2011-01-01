<?php

require_once dirname(dirname(__FILE__)) . '/application/One.php';

defined('DS') || define('DS', DIRECTORY_SEPARATOR);
defined('PS') || define('PS', PATH_SEPARATOR);

defined('ROOT_PATH') ||
    define('ROOT_PATH', realpath(dirname(dirname(__FILE__))));

defined('APPLICATION_ENV') ||
    ($env = getenv('APPLICATION_ENV')) ? define('APPLICATION_ENV', $env) :
        define('APPLICATION_ENV', 'production');

defined('APPLICATION_PATH') ||
    ($env = getenv('APPLICATION_PATH')) ? define('APPLICATION_PATH', $env) :
        define('APPLICATION_PATH', ROOT_PATH . DS. 'application');

set_include_path(implode(PS, array(
    realpath(ROOT_PATH . DS . 'externals' . DS . 'libraries'),
    realpath(APPLICATION_PATH . DS . 'code' . DS . 'core'),
    realpath(APPLICATION_PATH . DS . 'code' . DS . 'community'),
    realpath(APPLICATION_PATH . DS . 'code' . DS . 'local')
    )));

require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance()
    ->registerNamespace('One_Core')
;

try {
    One::app(null, APPLICATION_ENV)->bootstrap()
        ->run();
} catch (Exception $e) {
    echo $e->getMessage();
}


