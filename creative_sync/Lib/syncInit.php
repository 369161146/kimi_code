<?php

$initDirArr = explode('/', __DIR__);
define('ROOT_DIR', str_replace('/'.array_pop($initDirArr), '', __DIR__));
define('ROOT_OUT_DIR', str_replace('/'.array_pop($initDirArr), '', ROOT_DIR));
define('ROOT_PHPFW_DIR', str_replace('/'.array_pop($initDirArr), '', ROOT_OUT_DIR));
define('ROOT_DEV_DIR', str_replace('/'.array_pop($initDirArr), '', ROOT_PHPFW_DIR));
require_once ROOT_DIR.'/vendor/autoload.php';
require_once __DIR__.'/SplClassLoader.php';
require_once ROOT_DIR.'/Conf/global_param_conf.php';
require_once ROOT_DIR.'/Lib/Define.php';
require_once ROOT_DIR.'/Lib/Function.php';
require_once ROOT_DIR.'/vendor/thriftportal/ThriftEmail.php';

$classLoader = new SplClassLoader('Lib\Core', ROOT_DIR);
$classLoader->register();
$classLoader = new SplClassLoader('Model', ROOT_DIR);
$classLoader->register();
$classLoader = new SplClassLoader('Api', ROOT_DIR);
$classLoader->register();
$classLoader = new SplClassLoader('Helper', ROOT_DIR);
$classLoader->register();
$classLoader = new SplClassLoader('Pool', ROOT_DIR);
$classLoader->register();
$classLoader = new SplClassLoader('Advertiser', ROOT_DIR);
$classLoader->register();
$classLoader = new SplClassLoader('Cache', ROOT_DIR);
$classLoader->register();
$classLoader = new SplClassLoader('OutApi', ROOT_DIR);
$classLoader->register();
$classLoader = new SplClassLoader('Queue', ROOT_DIR);
$classLoader->register();
$classLoader = new SplClassLoader('Format', ROOT_DIR);
$classLoader->register();