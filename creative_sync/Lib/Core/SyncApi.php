<?php
namespace Lib\Core;
use Helper\CommonSyncHelper;
class SyncApi{
    
    private  $cookie_file = null;
    public $advertiserApiHttpCode;
    public $httpCode;
    public $httpError;
    
	function syncCurlGet($url,$isPost = 1,$timeOut = 60){
	    global $SYNC_ANALYSIS_GLOBAL;
	    $tmpCurlBeginTime = CommonSyncHelper::microtime_float();
	    
		if(empty($url)) return false;
		$ssl = substr($url, 0, 8) == "https://" ? true : false;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_TIMEOUT,$timeOut);
		if($isPost){
			curl_setopt($ch, CURLOPT_POST, 1);
		}
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if($ssl){
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		}
		$file_content = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->httpCode = $httpcode;
		$SYNC_ANALYSIS_GLOBAL['curl_time'] ++;
		CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"url: ".$url,2);
		if (curl_errno($ch)) {
		    $this->httpError = curl_error($ch);
			print "Error: Api Curl Error: " . $this->httpError ."\n";
			$SYNC_ANALYSIS_GLOBAL['curl_fail'] ++;
			return false;
		}
		curl_close($ch);
		$SYNC_ANALYSIS_GLOBAL['curl_success'] ++;
		$gerFunRuntime = CommonSyncHelper::getRunTime($tmpCurlBeginTime);
		echo "syncCurlGet curl run time: ".$gerFunRuntime."\n";
		return $file_content;
	}
		
	function syncCurlPost($url,$postData,$timeOut = 60){
	    global $SYNC_ANALYSIS_GLOBAL;
	    $tmpCurlBeginTime = CommonSyncHelper::microtime_float();
	    
	    if(empty($url) || empty($postData)) return false;
	    $ssl = substr($url, 0, 8) == "https://" ? true : false;
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_TIMEOUT,$timeOut);
	    curl_setopt($ch, CURLOPT_POST, 1);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
	    curl_setopt($ch,CURLOPT_URL,$url);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    if($ssl){
	        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	    }
	    $file_content = curl_exec($ch);
	    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	    $this->httpCode = $httpcode;
	    $SYNC_ANALYSIS_GLOBAL['curl_time'] ++;
	    CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"url: ".$url,2);
	    if (curl_errno($ch)) {
	        $this->httpError = curl_error($ch);
	        print "Error: Api Curl Error: " . $this->httpError."\n";
	        $SYNC_ANALYSIS_GLOBAL['curl_fail'] ++;
	        return false;
	    }
	    curl_close($ch);
	    $SYNC_ANALYSIS_GLOBAL['curl_success'] ++;
	    
	    $gerFunRuntime = CommonSyncHelper::getRunTime($tmpCurlBeginTime);
	    echo "syncCurlPost curl run time: ".$gerFunRuntime."\n";
	    return $file_content;
	}
	
	function syncCurlGet_Appia($url,$creds,$timeOut = 180){
	    global $SYNC_ANALYSIS_GLOBAL;
	    $tmpCurlBeginTime = CommonSyncHelper::microtime_float();
	    
		if(empty($url)) return false;
		$ssl = substr($url, 0, 8) == "https://" ? true : false;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_TIMEOUT,$timeOut);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if($ssl){
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER,array('Authorization: Basic ' . base64_encode($creds)));
		$result = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->httpCode = $httpcode;
		$SYNC_ANALYSIS_GLOBAL['curl_time'] ++;
		CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"url: ".$url,2);
		if (curl_errno($ch)) {
		    $this->httpError = curl_error($ch);
			print "Error: syncCurlGet_Appia Curl Error: " . $this->httpError ."\n";
			$SYNC_ANALYSIS_GLOBAL['curl_fail'] ++;
			return false;
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER,array('Authorization: Basic ' . base64_encode($creds)));
		$result = curl_exec($ch);
		curl_close($ch);
		$SYNC_ANALYSIS_GLOBAL['curl_success'] ++;
		
		$gerFunRuntime = CommonSyncHelper::getRunTime($tmpCurlBeginTime);
		echo "syncCurlGet_Appia curl run time: ".$gerFunRuntime."\n";
		return $result;
	}
        
    function  syncCurlGet_Instal($url,$headers = array(),$timeOut = 60){
        global $SYNC_ANALYSIS_GLOBAL;
        $tmpCurlBeginTime = CommonSyncHelper::microtime_float();
        
		if(empty($url)) return false;
		$ssl = substr($url, 0, 8) == "https://" ? true : false;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_TIMEOUT,$timeOut);
		
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if($ssl){
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		}
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
		$file_content = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->httpCode = $httpcode;
		$SYNC_ANALYSIS_GLOBAL['curl_time'] ++;
		CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"url: ".$url,2);
		if (curl_errno($ch)) {
		    $this->httpError = curl_error($ch);
			print "Error: Api Curl Error: " . $this->httpError ."\n";
			$SYNC_ANALYSIS_GLOBAL['curl_fail'] ++;
			return false;
		}
		curl_close($ch);
		$SYNC_ANALYSIS_GLOBAL['curl_success'] ++;
		
		$gerFunRuntime = CommonSyncHelper::getRunTime($tmpCurlBeginTime);
		echo "syncCurlGet_Instal curl run time: ".$gerFunRuntime."\n";
		return $file_content;
	}
        
    /**
     * 带验证auth请求API
     * @param string $url       请求地址
     * @param type $params      请求参数
     * @param type $method      GET or POST
     * @param type $t           超时时间
     * @param type $ct          链接时间
     * @param type $extheaders  HTTP头部信息
     * @param type $cookie      是否保存验证auth
     * @return type             请求返回数据
     */    
    public function syncCurlAuth($url, $params = array(), $method = 'GET', $t = 60, $ct = 30, $extheaders = array(), $cookie = false) {
        global $SYNC_ANALYSIS_GLOBAL;
        $tmpCurlBeginTime = CommonSyncHelper::microtime_float();
        $method = strtoupper($method);
        
        $ci = curl_init();
        curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);

        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, $ct);
        curl_setopt($ci, CURLOPT_TIMEOUT, $t);

        curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ci, CURLOPT_ENCODING, "");
        curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ci, CURLOPT_HEADER, false);

        $headers = (array) $extheaders;
        switch ($method) {
            case 'POST':
                curl_setopt($ci, CURLOPT_POST, TRUE);
                if (!empty($params)) {
                    $q = $this->encodeParams($params);
                    curl_setopt($ci, CURLOPT_POSTFIELDS, $q);
                }
                break;
            default:
                if (!empty($params)) {
                    $q = $this->encodeParams($params);
                    $url = $url . (strpos($url, '?') ? '&' : '?') . $q;
                }
                break;
        }
        curl_setopt($ci, CURLINFO_HEADER_OUT, TRUE);
        curl_setopt($ci, CURLOPT_URL, $url);
        if ($headers) {
            curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
        }
        if ($cookie) {
            //POST数据，获取COOKIE,cookie文件放在网站的temp目录下
            $this->cookie_file = tempnam('/tmp', 'cookie');
            curl_setopt($ci, CURLOPT_COOKIEJAR, $this->cookie_file);
        }
        if (!empty($this->cookie_file)) {
            curl_setopt($ci, CURLOPT_COOKIEFILE, $this->cookie_file);
        }
        
        $response = curl_exec($ci);
        $httpcode = curl_getinfo($ci, CURLINFO_HTTP_CODE);
        $this->httpCode = $httpcode;
        $SYNC_ANALYSIS_GLOBAL['curl_time'] ++;
        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"url: ".$url,2);
        if (curl_errno($ci)) {
            $this->httpError = curl_error($ci);
            print "Error: Api Curl Error: " . $this->httpError ."\n";
            $SYNC_ANALYSIS_GLOBAL['curl_fail'] ++;
            return false;
        }
        curl_close($ci);
        $SYNC_ANALYSIS_GLOBAL['curl_success'] ++;
        return $response;
    }
    
    public function kibanaPostCurl($url,$data,$timeOut){
        global $SYNC_ANALYSIS_GLOBAL;
        $tmpCurlBeginTime = CommonSyncHelper::microtime_float();
        if(empty($url)){
            return false;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT,$timeOut);
        curl_setopt($ch, CURLOPT_URL, $url);
        #curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch,CURLOPT_USERPWD, 'mob_report:Mobvista_256');
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $SYNC_ANALYSIS_GLOBAL['curl_time'] ++;
        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"url: ".$url,2);
        if (curl_error($ch)) {
            $this->httpError = curl_error($ch);
            print "Error: Api Curl Error: " . $this->httpError ."\n";
            $SYNC_ANALYSIS_GLOBAL['curl_fail'] ++;
            return false;
        }
        curl_close($ch);
        $SYNC_ANALYSIS_GLOBAL['curl_success'] ++;
        $gerFunRuntime = CommonSyncHelper::getRunTime($tmpCurlBeginTime);
        echo "kibanaBulkCurl curl run time: ".$gerFunRuntime."\n";
        return $result;
    }
    
    function syncCommonCurl($url,$isPost,$headArr = array(),$timeOut = 180){
        global $SYNC_ANALYSIS_GLOBAL;
        $tmpCurlBeginTime = CommonSyncHelper::microtime_float();
        if(empty($url)) return false;
        $ssl = substr($url, 0, 8) == "https://" ? true : false;
        $ch = curl_init();
        if($isPost){
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT,$timeOut);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if($ssl){
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        if(!empty($headArr) && is_array($headArr)){
            curl_setopt($ch, CURLOPT_HTTPHEADER,$headArr); //head ex: array('Authorization: Basic ' . base64_encode($creds))
        }
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->httpCode = $httpcode;
        $SYNC_ANALYSIS_GLOBAL['curl_time'] ++;
        CommonSyncHelper::syncEcho(__CLASS__,__FUNCTION__,"url: ".$url,2);
        if (curl_errno($ch)) {
            $this->httpError = curl_error($ch);
            print "Error: syncCommonCurl Curl Error: " . $this->httpError ."\n";
            $SYNC_ANALYSIS_GLOBAL['curl_fail'] ++;
            return false;
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        $SYNC_ANALYSIS_GLOBAL['curl_success'] ++;
        $gerFunRuntime = CommonSyncHelper::getRunTime($tmpCurlBeginTime);
        echo "syncCommonCurl curl run time: ".$gerFunRuntime."\n";
        return $result;
    }
    
    /**
     * 生成 URL-encode 之后的请求字符串
     * @param type $params
     * @return type
     */
    private  function encodeParams($params) {
        $s = '';
        if (!empty($params) && is_array($params)) {
            $s = http_build_query($params);
        }
        if (is_string($params)) {
            $s = $params;
        }
        return $s;
    }    
}