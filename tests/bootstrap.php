<?php
ob_start();
define("DEBUG", FALSE);
define("APP_PATH", str_replace(DIRECTORY_SEPARATOR, "/", dirname(dirname(__FILE__))));
date_default_timezone_set('Asia/Kolkata');
require_once APP_PATH . "/application/libraries/vendor/autoload.php";

// Include Test Directory
$paths = [ APP_PATH . "/tests" ];
$paths[] = get_include_path();
set_include_path(join(PATH_SEPARATOR, $paths));

// library's class autoloader
spl_autoload_register(function ($classname) {
    $path = str_replace("\\", DIRECTORY_SEPARATOR, $classname);
    $file = APP_PATH . "/application/libraries/{$path}.php";

    if (file_exists($file)) {
        require_once $file;
        return true;
    }
});

if (!function_exists('getallheaders')) { 
    function getallheaders() { 
        $headers = ''; 
        foreach ($_SERVER as $name => $value) { 
           if (substr($name, 0, 5) == 'HTTP_') { 
               $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value; 
           }
       }
       return $headers;
    }
}

// 2. load the Core class that includes an autoloader
require_once(APP_PATH. "/framework/core.php");
Framework\Core::initialize();

// plugins
$path = APP_PATH . "/application/plugins";
$iterator = new DirectoryIterator($path);

// 3. load and initialize the Configuration class 
$configuration = new Framework\Configuration(array(
    "type" => "ini"
));
Framework\Registry::set("configuration", $configuration->initialize());

// 4. load and initialize the Database class – does not connect
$database = new Framework\Database();
Framework\Registry::set("database", $database->initialize());

// 5. load and initialize the Cache class – does not connect
$cache = new Framework\Cache();
Framework\Registry::set("cache", $cache->initialize());

// 6. load and initialize the Session class 
$session = new Framework\Session();
Framework\Registry::set("session", $session->initialize());

unset($configuration);
unset($database);
unset($cache);
unset($session);
