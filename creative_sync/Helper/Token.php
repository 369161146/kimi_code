<?php
namespace Helper;
class Token {
    
    public static function setToken($params, $api_secret = API_SECRET) {
        $checkToken = $api_secret;
        ksort($params);
        $keys = array_keys($params);
        foreach ($keys as $key) {
            $checkToken .= self::filter($key);
        }
        $checkToken .= self::loop($params);
        $checkToken .= $api_secret;
        return md5($checkToken);
    }

    protected static function loop($params){
        $token = '';
        foreach ($params as $param) {
            if (is_array($param)) {
                $token .= self::loop($param);
            } else {
                $token .= self::filter($param);
            }
        }
        return $token;
    }

    protected static function filter($string){
        $string = trim((string)$string);
        if (!mb_detect_encoding($string, 'UTF-8', true)) {
            $string = mb_convert_encoding($string, 'UTF-8');
        }
        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8'); // 转换'与''与<与>与&这5个字符
        if (!get_magic_quotes_gpc()) {
            $string = addslashes($string); // 转义'与''与\与\0这4个字符
        }
        return $string;
    }
}
