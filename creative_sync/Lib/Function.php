<?php

function get($arr, $key, $default = '')
{
    return isset($arr[$key]) ? $arr[$key] : $default;
}

function get1($arr, $key, $function = 'trim')
{
    $value = isset($arr[$key]) ? $arr[$key] : '';
    $function && $value = $function($value);
    return $value;
}

function get3($arr, $key, $function = 'trim',$delVal)
{
    $value = isset($arr[$key]) ? $arr[$key] : '';
    $function && $value = $function($value);
    if(isset($delVal) && empty($value)){
        $value = $function($delVal);
    }
    return $value;
}

// 获取客户端ip
function get_ip()
{
    static $onlineip;
    if ($onlineip)
        return $onlineip;
    
    $onlineip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $HTTP_X_FORWARDED_FOR = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? array_map('trim', 
        explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])) : array(
        ''
    );
    
    if (ua_proxy() && validate_ip($HTTP_X_FORWARDED_FOR[0])) {
        // 代理浏览器，不论是否经过ELB，获取X_FORWARDED_FOR的第一个ip
        $onlineip = $HTTP_X_FORWARDED_FOR[0];
    } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && validate_ip($_SERVER['REMOTE_ADDR'])) {
        // 非ELB，并且能够获取真实ip，使用真实ip
        $onlineip = $_SERVER['REMOTE_ADDR'];
    } elseif ($HTTP_X_FORWARDED_FOR) {
        $HTTP_X_FORWARDED_FOR = array_reverse($HTTP_X_FORWARDED_FOR);
        foreach ($HTTP_X_FORWARDED_FOR as $ip) {
            if (validate_ip($ip)) {
                $onlineip = $ip;
                break;
            }
        }
    /**
     * // ELB的情况，使用X_FORWARDED_FOR的最后一个ip
     * $last_ip = array_pop($HTTP_X_FORWARDED_FOR);
     * if (validate_ip($last_ip)) {
     * $onlineip = $last_ip;
     * }
     */
    }
    return $onlineip;
}

/**
 * 使用代理浏览器
 *
 * @return boolean
 */
function ua_proxy()
{
    $ua = get($_SERVER, 'HTTP_USER_AGENT');
    if (!$ua)
        return false;
    if (stripos($ua, 'Opera') !== false || stripos($ua, 'UC') !== false || stripos($ua, 'QQ') !== false ||
         stripos($ua, 'googleweblight') !== false) {
        return true;
    } else {
        return false;
    }
}

function create_proxy_ua($os_version, $device_model, $platform, $language)
{
    $platformId = get_platform_id($platform);
    $platformId == 0 && $platform = PLATFORM_ANDROID_NAME;
    $platform = ucfirst($platform);
    $language = $language ? $language : 'en-US';
    $device_model = $device_model ? $device_model : 'GS5';
    $ua = "Mozilla/5.0 (Linux; U; {$platform} {$os_version}; {$language}; {$device_model} Build/KVT49L) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 UCBrowser/10.5.2.582 U3/0.8.0 Mobile Safari/534.30";
    return $ua;
}

// 验证一个真实的外网ip
function validate_ip($ip)
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function base64encode($data)
{
    $str = base64_encode($data);
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
    $chars1 = "vSoajc7dRzpWifGyNxZnV5k+DHLYhJ46lt0U3QrgEuq8sw/XMeBAT2Fb9P1OIKmC";
    $chars_combine = array_combine(str_split($chars), str_split($chars1));
    
    return strtr($str, $chars_combine);
}

function base64decode($data)
{
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
    $chars1 = "vSoajc7dRzpWifGyNxZnV5k+DHLYhJ46lt0U3QrgEuq8sw/XMeBAT2Fb9P1OIKmC";
    $chars_combine = array_combine(str_split($chars1), str_split($chars));
    $str = strtr($data, $chars_combine);
    $return = base64_decode($str);
    return rtrim(base64_encode($return), '=') == rtrim($str, '=') ? $return : '';
}

//here new begin...
/**
 * platfrom map platform id
 * @return multitype:number
 */
function getPlatform(){
    $platformArr = array(
        'android' => 1,
        'ios' => 2,
        'site' => 3,
        'h5_link' => 4,
    );
    return $platformArr;
}

/**
 * echo function
 * @param unknown $class
 * @param unknown $function
 * @param unknown $logContent
 * @param number $logType 1：Error 2:Normal
 * @return boolean
 */
function syncEcho($class = '',$function = '',$logContent,$logType = 1){
    if(empty($logContent) || empty($logType)){
        return false;
    }
    if(!in_array($logType, array(1,2))){
        echo "Error: ".__CLASS__." ".__FUNCTION__." logType 1:Error echo type 2:Common echo type\n";
        return false;
    }
    if($logType == 1){
        $logType = 'Error';
    }elseif($logType == 2){
        $logType = 'Normal';
    }
    if(empty($class) && empty($function)){
        $echoStr = $logType.': '.$logContent." time: ".date('Y-m-d H:i:s')."\n";
    }else{
        $echoStr = $logType.': '.$class.'=>'.$function.":: ".$logContent." time: ".date('Y-m-d H:i:s')."\n";
    }
     
    if(!empty($echoStr)){
        echo $echoStr;
        return true;
    }
    return false;
}

/**
 * 获取图片类型
 * 索引 2 是图像类型的标记：1 = GIF，2 = JPG，3 = PNG，4 = SWF，5 = PSD，6 = BMP，7 = TIFF(intel byte order)，8 = TIFF(motorola byte order)，9 = JPC，10 = JP2，11 = JPX，12 = JB2，13 = SWC，14 = IFF，15 = WBMP，16 = XBM。这些标记与 PHP 4.3.0 新加的 IMAGETYPE 常量对应。索引 3 是文本字符串，内容为“height="yyy" width="xxx"”，可直接用于 IMG 标记。
 * @param unknown $file
 * @return boolean
 */
function checkImageType($file){
	if(!file_exists($file)){
		return false;
	}
	$info = getimagesize($file);
	if(empty($info[2])){
		return false;
	}
	return $info[2];
}
