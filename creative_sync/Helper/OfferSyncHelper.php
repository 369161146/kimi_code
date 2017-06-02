<?php
namespace Helper;
use Lib\Core\SyncHelper;
use Lib\Core\SyncConf;
class OfferSyncHelper extends SyncHelper{
	
    public static function platforms() {
        return array(
            1 => 'Android',
            2 => 'iOS',
            3 => 'Site',
            4 => 'H5 Link',
        );
    }

    public static function price_types($cpa = 0) {
        $types = array(
            1 => 'CPI',
            2 => 'CPC',
            3 => 'CPM',
        );
        if ($cpa == 1) unset($types[1]);
        return $types;
    }

    public function hours() {
        $hours = array();
        for ($i = 0; $i < 24; $i++) {
            if ($i < 10) $i = '0' . $i;
            $hours[] = $i;
        }
        return $hours;
    }

    public static function weeks() {
        return array(
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        );
    }

    public static function campaign_types() {
        return array(
            0 => '- Select -',
            // 1 => 'AppStore',
            2 => 'GooglePlay',
            3 => 'APK',
            4 => 'Other',
        );
    }

    public static function app_category(){
    	return array(
    			1 => 'Application',
    			2 => 'Game'
    	);
    }
        
    public static function special_types() {
        $category = array (
            1 => 'Apk',
            2 => 'Adult',
            3 => 'Incent',
            4 => 'Brand',
            5 => 'CPA',
            6 => 'Game',
            7 => 'Business',
            8 => 'Booster',
            9 => 'Laucher',
            10 => 'Browser',
            11 => 'Comics',
            12 => 'Communication',
            13 => 'Education',
            14 => 'Finance',
            15 => 'Health And Fitness',
            16 => 'Wallpaper',
            17 => 'Media And Video',
            18 => 'Medical',
            19 => 'Music And Audio',
            20 => 'News And Magazines',
            21 => 'Photography',
            22 => 'E-Commerce',
            23 => 'Social',
            24 => 'Sports',
            25 => 'Transportation',
            26 => 'Travel And Local',
            27 => 'Weather',
            28 => 'Dictionary',
            29 => 'Book',
            30 => 'Keyboard',
            31 => 'Battery Saver',
            32 => 'Others',
            33 => 'App Market',
            // 34 => 'Vehicle',  //已经弃用
            35 => 'Job Searching',
            36 => 'Mobile Recharge',
            37 => 'Screen Lock',
            38 => 'Privacy Tool'
        );
        asort($category);
        return $category;
    }
    
    public static function networks() {
        return array(
            1 => '3S',
            2 => 'Hasoffer',
            3 => 'Neverblue',
            6 => 'Appflood',
            7 => 'clickdealer',
            8 => 'motiveinteractive',
            9 => 'startapp',
            10 => 'Cake',
            11 => 'Other',
            21 => 'glispa',
            22 => 'pubnative',
            23 => 'ironsource',
            24 => 'avazu',
            25 => 'mobilecore',
            26 => 'gomo',
            27 => 'appnext',
            28 => 'appia',
            29 => 'mobvistaAdn',
        	30 => 'supersonic',
        	31 => 'applift',
        	32 => 'appcoach',
            33 => 'motivefeed',
            34 => 'tabatoo',
            35 => 'artofclick',
            36 => 'adways',
            37 => 'youappi',
            38 => 'ringtonepartner',
            39 => 'affle',
            40 => 'newstartapp',
            41 => 'appthis',
            42 => 'taptica',
            43 => 'Instal',
        );
    }
    
    public static function ios_category(){
        return array(
            0 => 'books',
            1 => 'business',
            2 => 'catalogs',
            3 => 'education',
            4 => 'entertainment',
            5 => 'finance',
            6 => 'food & drink',
            7 => 'games',
            8 => 'health & fitness',
            9 => 'lifestyle',
            10 => 'medical',
            11 => 'music',
            12 => 'navigation',
            13 => 'news',
            14 => 'magazines & newspapers',
            15 => 'photo & video',
            16 => 'productivity',
            17 => 'reference',
            18 => 'shopping',
            19 => 'social networking',
            20 => 'sports',
            21 => 'travel',
            22 => 'utilities',
            23 => 'weather',
        );
    }
    
    public static function sources() {
        return array();
    }
    
    
    public static function getOsVersionCode($versionName) {
    	$version = explode(".", $versionName);
    	$versionCode = 0;
    	for ($i = 0; $i < 4; $i ++) {
    		$versionCode = $versionCode * 100 + (int)(isset($version[$i]) ? $version[$i] : 0);
    	}
    	return $versionCode;
    }
    
    public static function android_versions() {
        return array(
            1 => '1.1',
            2 => '1.5',
            3 => '1.6',
            4 => '2.0',
            5 => '2.1',
            6 => '2.2',
            7 => '2.3',
            8 => '3.0',
            9 => '3.1',
            10 => '3.2',
            11 => '4.0',
            12 => '4.1',
            13 => '4.2',
            14 => '4.3',
            15 => '4.4',
            //16 => '4.5', //没有4.5这个版本
            17 => '5.0',
            18 => '5.1',
            19 => '6.0',
            20 => '7.0',
            21 => '99.0',
        );
    }
    
    public static function ios_versions() {
    	return array(
    		1 => '2.0',
			2 => '2.1',
			3 => '2.2',
			4 => '3.0',
			5 => '3.1',
			6 => '3.2',
			7 => '4.0',
			8 => '4.1',
			9 => '4.2',
			10 => '4.3',
			11 => '5.0',
			12 => '5.1',
			13 => '6.0',
			14 => '7.0',
			15 => '7.0.1',
			16 => '7.0.2',
			17 => '7.0.3',
			18 => '7.0.4',
			19 => '7.0.5',
			20 => '7.0.6',
			21 => '7.1',
			22 => '7.1.1',
			23 => '7.1.2',
			24 => '8.0',
    	    25 => '9.0',
    	    26 => '9.2',
    	    27 => '99.0',
    	);
    }

    public static function statuses(){
        return array(
            1 => 'Active',
            2 => 'Paused',
            3 => 'Deleted',
            4 => 'Pending',
            5 => 'Rejected',
            6 => 'Unfinished',
            7 => 'Review',
            8 => 'Out of Daily Budget',
            9 => 'Out of Total Budget',
            10 => 'Out of Balance',
            11 => 'Out of Daily Cap',
            12 => 'Advertiser Pause Active',
            13 => 'Advertiser Pause Pending',
            14 => 'Error Campaign',
            15 => 'Displayed Error', //app_name乱码或者app_desc乱码单的状态
        );
    }

    public static function creative_statuses() {
        return array(
            1 => 'Active',
            2 => 'Paused',
            3 => 'Deleted',
            4 => 'Pending',
            5 => 'Disapproved',
            7 => 'Review',
            14 => 'Error Creative',
        );
    }

    public static function creative_sizes() {
        return array(
            '320x50',
            '300x250',
        	'300x300',
            '480x320',
            '320x480',
            '1200x627',
            '1200x628',
        );
    }
    
    public static function sync_offer_creative_types() {
        return array(
            '128x128' => 1,
            '320x50' => 2,
            '300x250' => 4,
            '300x300' => 41,
            '1200x627' => 42,
            '480x320' => 5,
            '320x480' => 6,
            'rewarded_video' => 94,
			'feeds_video' => 95,
            "240x350"=>101,
			"390x200"=>102,
			"560x750"=>103,
			"750x560"=>104,
        );
    }
    
    public static function creative_types_sizes() {
        return array(
            'icon' => array('96x96', '128x128'),
            'banner' => array('320x50', '468x60'),
            'overlay' => array('400x300', '300x250'),
            'full_screen' => array('480x320', '320x480'),
        	'icon_300' => array('480x320', '320x480'),
        );
    }

    public static function creative_types(){
        $db = new Model();
        $sql = "SELECT id,title FROM `adtype`";
        return $db->formatList($sql, 'id', 'title');
    }

    public static function getCountryList() {
        $db = new Model();
        $sql = "SELECT short,`name` FROM `geo` WHERE short != '**' ORDER BY `name` ASC";
        return $db->formatList($sql, 'short', 'name');
    }

    public static function adtype_html($ad_types, $field = 'adtype') {
    	return false;
        /* $adtype = Input::get($field, array());
        $focus = '';
        if ($adtype) $focus = ' focus';
        $adtype_html = '<select name="' . $field . '[]"' . $focus . ' class="form-control chosen-select' . $focus . '" multiple data-placeholder="- Scenario -" id="' . $field . '">';
        $adtype_html .= '<option value="">- Scenario -</option>';

        $adtype_html .= '<optgroup label="Android">';
        foreach ($ad_types as $key => $val) {
            if (strpos($val, 'Wap') !== FALSE || strpos($val, 'API') !== FALSE) continue;
            $selected = '';
            if (in_array($key, $adtype)) $selected = ' selected="selected"';
            $adtype_html .= '<option value="' . $key . '"' . $selected . '>' . $val . '</option>';
        }
        $adtype_html .= '</optgroup>';

        $adtype_html .= '<optgroup label="API">';
        foreach ($ad_types as $key => $val) {
            if (strpos($val, 'API') === FALSE) continue;
            $selected = '';
            if (in_array($key, $adtype)) $selected = ' selected="selected"';
            $adtype_html .= '<option value="' . $key . '"' . $selected . '>' . $val . '</option>';
        }
        $adtype_html .= '</optgroup>';

        $adtype_html .= '<optgroup label="Wapsite">';
        foreach ($ad_types as $key => $val) {
            if (strpos($val, 'Wap') === FALSE) continue;
            $selected = '';
            if (in_array($key, $adtype)) $selected = ' selected="selected"';
            $adtype_html .= '<option value="' . $key . '"' . $selected . '>' . $val . '</option>';
        }
        $adtype_html .= '</optgroup>';

        $adtype_html .= '</select>';
        return $adtype_html; */
    }

    public static function creative_langs($zero = 0) {
        $langs = array(
            1 => 'English',
            2 => 'Arabic',
            3 => 'Portuguese',
            4 => 'Spanish',
            5 => 'Vietnamese',
            6 => 'Thai',
            7 => 'Indonesian',
            8 => 'Russian',
            9 => 'German',
            10 => 'French',
            11 => 'Japanese',
            12 => 'Korean',
            13 => 'Turkish',
            14 => 'Traditional Chinese',
            15 => 'Simplified Chinese',
            16 => 'Italian',
            17 => 'Traditional Chinese (Hong Kong)',
            18 => 'Traditional Chinese (Taiwan)',
            19 => 'Norway',
            20 => 'Sweden',
        );
        asort($langs);

        if ($zero) {
            $temp = array(
                0 => '- Select -'
            );
            foreach ($langs as $key => $val) {
                $temp[$key] = $val;
            }
            $langs = $temp;
        }
        return $langs;
    }

    public static function tags() {
        return array(
            1 => 'Manager',
            2 => 'Affiliate',
        );
    }

    public static function devices() {
        require APP_DIR . 'config/devices.php';
        $list = array();
        foreach ($devices as $key => $val) {
            $i = 0;
            $children = array();
            foreach ($val as $k => $v) {
                $i++;

                if ($i == 1) {
                    $id = $k;
                } else {
                    $children[] = array(
                        'id' => $k,
                        'text' => $v,
                    );
                }
            }
            $list[] = array(
                'id' => $id,
                'text' => $key,
                'children' => $children,
            );
        }
        return json_encode($list);
    }

    public static function getOperatorJson() {
        include APP_DIR . 'config/operations.php';
        $json_arr = array();
        $temp_arr = array();
        foreach($operations as $operator) {
            $temp['id'] = $operator[0];
            $temp['text'] = $operator[1] . "(" . $operator[0] . ")";
            $temp_arr[$operator[2]][] = $temp;
        }
        $count = 111110;
        foreach($temp_arr as $key => $v) {
            $json['id'] = $count;
            $count++;
            $json['text'] = $key;
            $json['children'] = $v;
            $json_arr[] = $json;
        }
        return json_encode($json_arr);
    }

    public static function getOperatorIdArr() {
        $json = self::getOperatorJson();
        $operator_arr = json_decode($json, true);
        $return_arr = array();
        foreach($operator_arr as $p) {
            $temp = array();
            if(is_array($p['children'])) {
                foreach($p['children'] as $c) {
                    $temp[] = $c['id'];
                }
            }
            $return_arr[$p['id']] = $temp;
        }
        return $return_arr;
    }

    public static function remoteCopy($img, $path = 'image/jpeg', $type = 'common'){
        header('content-type:text/html;charset=utf8');
        $ch = curl_init();
        //加@符号curl就会把它当成是文件上传处理
        $data = array('img'=>'@'. $img,$path);
        curl_setopt($ch,CURLOPT_URL,"http://rtbpic.mobvista.com/upload_date.php?type=" . $type);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result,true);
    }

}
