<?php
define('NAME_ZH', '迷你云');
define('NAME_EN', 'MyCloud');
define('APP_VERSION',"1.7.1");
//
// 适配无REQUEST_URI的情况
//
if (!isset($_SERVER['REQUEST_URI'])) {
    if (isset($_SERVER['argv'])) {
        $uri = $_SERVER['PHP_SELF'] . '?' . $_SERVER['argv'][0];
    } else {
        $uri = $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'];
    }
    $_SERVER['REQUEST_URI'] = $uri;
}


function my_session_start()
{
    if (isset($_COOKIE['PHPSESSID'])) {
        $sessionID = $_COOKIE['PHPSESSID'];
    } else if (isset($_GET['PHPSESSID'])) {
        $sessionID = $_GET['PHPSESSID'];
    } else {
        session_start();
        return false;
    }

    if (!preg_match('/^[a-z0-9]{32}$/', $sessionID)) {
        return false;
    }
    session_start();

    return true;
}

my_session_start();

//定义支持的多国语言列表
$languageList = array("zh_cn" => "简体中文", "zh_tw" => "繁體中文", "en" => "English");

// 增加版本逻辑
$path = dirname(__FILE__) . "/../../upload/"; //默认存储路径
$configPath = dirname(__FILE__) . '/miniyun-config.php';



//
// 共享memcache时namespace
//
define('MEMCACHE_KEY', '');

//配置文件存在的话，进行系统初始化操作
$initialized = false; //系统是否初始化
$tablePrefix = "miniyun_";
$key = "key";
if (file_exists($configPath)) {
    require_once $configPath;
    $initialized = true;
    $tablePrefix = DB_PREFIX . "_";
    $key = KEY;
    $path = BASE;
    if (!defined('DB_TYPE') || DB_TYPE == "mysql") {
        $dbConfig = array(
            'connectionString' => 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';port=' . DB_PORT . ";",
            'emulatePrepare' => true,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'charset' => DB_CHARSET,
            'schemaCachingDuration' => 3600,
            'enableProfiling' => true,
            'enableParamLogging' => true,
        );
    }
} else {
    if (!empty($_SESSION)) {
        unset($_SESSION['user']);
    }
}

$hook_path = dirname(__FILE__) . '/../common/hook/hook.php';
require_once($hook_path);


//用户基础地址
$baseUri = substr($_SERVER['SCRIPT_NAME'], 0, strlen($_SERVER['SCRIPT_NAME']) - 10) . "";


$config = array(
    'basePath' => dirname(__FILE__) . DIRECTORY_SEPARATOR . '..',
    'name' => 'Miniyun',
    'preload' => array('log', 'initialize'),
    'import' => array(
        'application.models.*',
        'application.models.db.*',
        'application.models.install.*',
        'application.components.*',
        'application.models.chooser.*',
        'application.models.message.*',
        'application.utils.*',
        'application.common.*',
        'application.common.api.*',
        'application.common.filters.*',
        'application.common.managers.*',
        'application.common.plugin.*',
        'application.common.hook.*',
        'application.common.hash.*',
        'application.common.fileSystem.*',
        'application.common.fileSystem.implement.*',
        'application.common.migrate.*',
        'application.common.privilege.*',
        'application.common.userValid.*',
        'application.extensions.image.*',
        'application.extensions.image.drivers.*',
        'application.extensions.image.helpers.*',
        'application.extensions.phpseclib.*',
        'application.extensions.oauth2.lib.*',
        'application.extensions.oauth2.server.pdo.*',
        'application.extensions.oauth2.server.pdo.lib.*',

        //api部分所需要自动加载的文件夹
        'application.api.*',
        'application.api.common.*',
        'application.api.common.interface.*',
        'application.api.controller.*',
        'application.api.controller.account.*',
        'application.api.controller.device.*',
        'application.api.controller.fileops.*',
        'application.api.controller.fileops.after.*',
        'application.api.controller.files.*',
        'application.api.controller.oauth2.*',
        'application.api.controller.revisions.*',
        'application.api.controller.security.*',
        'application.api.controller.thumbnails.*',
        'application.api.controller.version.*',
        'application.api.filter.*',
        'application.api.model.*',

        //加载Cache部分
        'application.cache.*',
        'application.cache.biz.*',
        'application.cache.model.*',
        //加载2级Cache部分
        'application.cache2.*',
        'application.cache2.biz.*',
        'application.cache2.model.*',
        //加载服务
        'application.lianyu.*',
        'application.lianyu.service.*',
        'application.lianyu.biz.*',
        'application.lianyu.biz.downloadPackage.*',
        'application.lianyu.util.*',
        'application.caoyu.*',
        'application.caoyu.service.*',
        'application.caoyu.biz.*',
        'application.caoyu.util.*',
        'application.jiyu.*',
        'application.jiyu.service.*',
        'application.jiyu.biz.*',
        'application.jiyu.util.*',
        'application.lianyu.share.*',
    ),
    'components' => array(
        'errorHandler' => array(
            'errorAction' => 'site/error',
        ),
        'user' => array(
            'allowAutoLogin' => true,
        ),
        'request' => array(
            'enableCookieValidation' => true,
        ),
        'session' => array(
            'class' => 'CHttpSession',
            'timeout' => 1800,
        ),
        'db' => $dbConfig,
        'initSQLs' => array(
            'set global max_allowed_packet = 15728640', //迷你文档转换成功的最大文本单文件不能超15M
        ),
        'modules' => array(),
        'log' => array(
            'class' => 'CLogRouter',
            'routes' => array(
                array(
                    'class' => 'CFileLogRoute',
                    'levels' => 'error, warning',
                    'logFile' => 'error.log',
                ),
                array(
                    'class' => 'CFileLogRoute',
                    'levels' => 'error, warning',
                    'categories' => 'system.db.*',
                    'logFile' => 'sql.log',
                ),
                array(
                    'class' => 'CFileLogRoute',
                    'levels' => 'error, warning',
                    'categories' => 'miniyun.cache*',
                    'logFile' => 'cache.log',
                ),
                array(
                    'class' => 'CFileLogRoute',
                    'levels' => 'warning,error',
                    'categories' => 'miniyun.api',
                    'logFile' => 'api.log',
                ),
                array(
                    'class' => 'CFileLogRoute',
                    'levels' => 'error, warning',
                    'categories' => 'miniyun.miniStore.*',
                    'logFile' => 'miniStore.log',
                )
            )
        ),
        'hook' => array(
            'class' => 'application.common.hook.MHookComponent',
        ),
        'data' => array(
            'class' => 'application.common.fileSystem.MFilesystem',
        ),
        'privilege' => array(
            'class' => 'application.common.privilege.MPrivilege',
        ),
        'image' => array(
            'class' => 'application.extensions.image.CImageComponent',
            'driver' => 'GD', // GD or ImageMagick
        ),
        //
        // 增加rest风格逻辑
        //
        'urlManager' => array(
            'urlFormat' => 'path',
            'showScriptName' => true,
            'rules' => array(
                //
                // REST patterns
                //
                array('api/<action>', 'pattern' => 'api/1/<action>.*?'),
                //
                // Other controllers
                //
                '<controller:\w+>/<action:\w+>' => '<controller>/<action>',
            ),
        ),
        'autoUpdateFlash' => false,
    ),

    // using Yii::app()->params['paramName']
    'params' => array(
        'adminEmail' => 'webmaster@example.com',
        'tablePrefix' => $tablePrefix, //表前缀
        'defaultAvatar' => '/static/images/default-avatar.png', //默认头像
        'app' => array( //app信息描述
            'initialized' => $initialized, //系统是否初始化
            'key' => $key, //系统key
            'path' => $path, //默认存储路径
            'apiVersion' => "1", // api 版本
            'uploadSize' => (int)ini_get("upload_max_filesize") < (int)ini_get("post_max_size") ? ini_get("upload_max_filesize") * 1024 * 1024 : ini_get("post_max_size") * 1024 * 1024,
            'language' => $languageList, // 语言
        ),
        //
        // office文档类型
        //
        'officeType' => array(
            't0' => 'application/msword',
            't1' => 'application/msexcel',
            't2' => 'application/mspowerpoint',
            't3' => 'application/pdf',
            't4' => 'text/plain',
            't5' => 'application/rtf',
        ),


    ),
);
//读取memcache.php的配置文件
if ($initialized) {
    $openCache = false;
    $memcacheConfigPath = MINIYUN_PATH . "/protected/config/memcache.php";
    if (file_exists($memcacheConfigPath)) {
        $memcacheConfig = include $memcacheConfigPath;
        if (is_array($memcacheConfig) && count($memcacheConfig["servers"]) > 0) {
            $config["components"]["memcache"] = $memcacheConfig;
            $openCache = true;
        }
    }
    if ($openCache) {
        define('MEMCACHE_ENABLED', true);
    } else {
        define('MEMCACHE_ENABLED', false);
    }
} else {
    define('MEMCACHE_ENABLED', false);
}
return $config;