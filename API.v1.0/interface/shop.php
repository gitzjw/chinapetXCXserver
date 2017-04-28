<?php

/**
 * Created by PhpStorm.
 * User: lining
 * Date: 15/3/3
 * Time: 上午11:11
 */
class shop
{
    public function __construct()
    {
        $this->DB = dbLink();
        include 'configs/init_config.php';
        if ($_SERVER['HTTP_HOST'] != $release_domain) {
            include 'configs/configs.inc.beta.php';
        } else {
            include 'configs/configs.inc.php';
        }
        $this->erp_path = $erp_path;
        $this->db_name = $erp_config['db_name'];

    }

    /**
     * 接口访问post函数
     * @return mixed    返回接受到的数据
     */
    private function urlpost($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    public function goods()
    {

        $menus_name = "good" . date("Y-m-d", time());
        $memcache = new Memcache;
        $memcache->connect('127.0.0.1', 11222) or die ("Could not connect");
        $menus = $memcache->get($menus_name);
        if (empty($menus)) {
            $menus = array();
            $catSql = "SELECT cat_id,cat_name  FROM `" . $this->db_name . "category` ORDER BY sort_order";
            $catList = $this->DB->get_all($catSql);

            foreach ($catList as $k => $cat) {
                $menus[$k]['id'] = $cat['cat_id'];
                $menus[$k]['tag'] = $cat['cat_name'];
                $menus[$k]['name'] = $cat['cat_name'];
                $goodSql = "SELECT goods_id, cat_id,goods_sn ,goods_name,goods_number,shop_price,virtual_sales ,goods_img FROM `" . $this->db_name . "goods` WHERE cat_id = {$cat['cat_id']} ";
                $goodList = $this->DB->get_all($goodSql);
                $dishs = array();
                foreach ($goodList as $key => $good) {
                    $str_cut = ($good['goods_name']);
                    $dishs[$key]['name'] = $this->substr_cut($str_cut, 12);
                    $dishs[$key]['id'] = intval($good['goods_id']);
                    $dishs[$key]['price'] = (int)$good['shop_price'];
                    $dishs[$key]['sales'] = $good['virtual_sales'];
                    $dishs[$key]['pic'] = "https://lechongwu.cn/" . $good['goods_img'];
                    $dishs[$key]['count'] = 0;
                }

                $menus[$k]['dishs'] = $dishs;
            }
            $menus = json_encode($menus);
            $status = $memcache->set($menus_name, $menus, false, 43200);//半天
        }
        echo $menus;
        exit;
    }

    /**
     * 图片地址替
     * @param string $content 内容
     * @param string $suffix 增加
     */
    function get_img_thumb_url($content = "", $suffix = "https://lechongwu.cn")
    {
        $pregRule = "/<[img|IMG].*?src=[\'|\"](.*?(?:[\.jpg|\.jpeg|\.png|\.gif|\.bmp]))[\'|\"].*?[\/]?>/";
        $content = preg_replace($pregRule, '<img src="' . $suffix . '${1}" >', $content);
        return $content;
    }


    public function goodinfo()
    {
        $goodId = intval($_REQUEST['id']);
        if (empty($goodId)) {
            $data = array(
                'status' => 403,
                'msg' => '没有商品详情',
            );
            echo json_encode($data);
            exit;
        }
        $sql = "SELECT original_img,shop_price,goods_name,virtual_sales,goods_number,goods_desc FROM `" . $this->db_name . "goods`  WHERE goods_id = '$goodId'";
        $goodinfo = $this->DB->get_one($sql);
        $imgsql = "SELECT img_url FROM " . $this->db_name . "goods_gallery  WHERE goods_id = '$goodId'";
        $imgdata = $this->DB->get_all($imgsql);
        foreach ($imgdata as $k => $v) {
            $imgdata[$k] = "https://lechongwu.cn/" . $v['img_url'];
        }
        $goodinfo['good_img'] = $imgdata;

        #解析詳情圖片
        $content = $goodinfo['goods_desc'];
        $newct = $this->get_img_thumb_url($content);
        $goodinfo['goods_desc'] = $newct;


        if ($goodinfo) {
            $data = array(
                'status' => 200,
                'data' => $goodinfo,
            );
        } else {
            $data = array(
                'status' => 403,
                'msg' => '没有商品详情',
            );
        }
        echo json_encode($data);
        exit;
    }

    public function onLogin()
    {
        $userInfo = json_decode($_REQUEST['userInfo']);

        $username = $userInfo->nickName;
//        $province = $userInfo->province;
//        $city = $userInfo->city;
        $avatar = $userInfo->avatarUrl;
        $code = $_REQUEST['code'];
        $appid = "wx9d0d76ce43dab283";
        $AppSecret = "04860cc568204ffdfa23dadc17025720";
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid=" . $appid . "&secret=" . $AppSecret . "&js_code=" . $code . "&grant_type=authorization_code";
        $session = $this->urlpost($url);
        $sessionobj = json_decode($session);
        if (empty($sessionobj->openid)) {
            $data = array(
                'status' => 403,
                'msg' => 'openid获取失败',
            );
            echo json_encode($data);
            exit;
        }

        $openid = $sessionobj->openid;
        //根据openid查询用户社区信息 不存在则创建
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=xcxlogon&version=d1bd83a33f1a841ab7fda32449746cc4&opid= " . $openid . "&time=" . time();

        $bbs_user = $this->urlpost($url);
        $bbs_user = json_decode($bbs_user, true);


        if ($bbs_user['status'] != 200) {
            $data = array(
                'status' => 403,
                'msg' => 'bbs社区身份创建获取失败',
            );
            echo json_encode($data);
            exit;
        }
        //key opid 加 session_key 为value 保存在redis中 过去时间1h
        $rd_session = exec("head -n 80 /dev/urandom | tr -dc A-Za-z0-9 | head -c 168");
        $re_data =array($sessionobj->openid,$sessionobj->session_key,$bbs_user['uid']);
        $rd_value = json_encode($re_data);
        $memcache = new Memcache;
        $memcache->connect('127.0.0.1', 11222) or die ("Could not connect");
        $status = $memcache->set($rd_session, $rd_value, false, 3600);//3600
        if (empty($status)) {
            $data = array(
                'status' => 403,
                'msg' => 'rd_session失败',
            );
            echo json_encode($data);
            exit;
        }

        //根据openid查询用户商城信息(地址) 不存在则创建
        $user_sql = "SELECT user_id FROM `" . $this->db_name . "users` WHERE openid = '{$openid}'";
        $userinfo = $this->DB->get_one($user_sql);
        if (empty($userinfo['user_id'])) {
            $userdata = array(
                'user_name' => $username,
                'openid' => $sessionobj->openid,
                'reg_time' => time()
            );
            $userStatus = $this->DB->insert($this->db_name . 'users', $userdata, false);
            if (empty($userStatus)) {
                $data = array(
                    'status' => 403,
                    'msg' => '创建用户数据失败',
                );
                echo json_encode($data);
                exit;
            }
        } else {

            $last = array('last_time' => date("Y-m-d H:i:s", time()));
            $where = "user_id = " . $userinfo['user_id'];
            $this->DB->update($this->db_name . 'users', $last, $where);
        }
        $data = array(
            'status' => 200,
            'msg' => 'success',
            'rd_session' => $rd_session,
            'bbs_name' => $bbs_user['name'],
            'uc_avatarUrl' => $bbs_user['uc_avatarUrl'],
        );

        echo json_encode($data);
    }

    public function checkId()
    {
        $key = trim(addslashes($_REQUEST['re_session']));
        $memcache = new Memcache;
        $memcache->connect('127.0.0.1', 11222) or die ("Could not connect");
        $rd_value = $memcache->get($key);

        if (empty($rd_value)) {
            $data = array(
                'status' => 404,
                'msg' => '登录超时，请到个人中心刷新登录',
            );
            echo json_encode($data);
            die;
        }
        $valueData = json_decode($rd_value);
        $openid = $valueData['0'];
        $session_key = $valueData['1'];
        $bbs_userid = $valueData['2'];
        $user_sql = "SELECT user_id,openid,user_name FROM `" . $this->db_name . "users` WHERE openid = '{$openid}'";
        $userinfo = $this->DB->get_one($user_sql);
        if (empty($userinfo['user_id'])) {
            $data = array(
                'status' => 404,
                'msg' => '用户不存在',
            );
            echo json_encode($data);
            die;
        } else {
            return $userinfo;
        }

    }

    public function address()
    {
        $userinfo = $this->checkId();
        $addr_sql = "SELECT consignee,mobile,address FROM `" . $this->db_name . "user_address` WHERE user_id =" . $userinfo['user_id'];
        $userAddr = $this->DB->get_one($addr_sql);

        $data = array(
            'status' => 200,
            'msg' => "succes",
            'address' => $userAddr
        );
        echo json_encode($data);


    }

    public function addAddress()
    {

        $allvalue = json_decode($_GET['allvalue']);
        if (empty($allvalue->consignee) or empty($allvalue->mobile) or empty($allvalue->address)) {
            $data = array(
                'status' => 404,
                'msg' => '亲填写每一项地址信息',
            );
            echo json_encode($data);
            die;
        }

        $userinfo = $this->checkId();
        $addr_sql = "SELECT consignee,mobile,address FROM `" . $this->db_name . "user_address` WHERE user_id =" . $userinfo['user_id'];
        $userAddr = $this->DB->get_one($addr_sql);
        if ($userAddr) {
            $adderssdata = array(
                'consignee' => $allvalue->consignee,
                'mobile' => $allvalue->mobile,
                'address' => $allvalue->address,
            );
            $where = "user_id = " . $userinfo['user_id'];
            $addStatus = $this->DB->update($this->db_name . 'user_address', $adderssdata, $where);

        } else {
            $adderssdata = array(
                'consignee' => $allvalue->consignee,
                'mobile' => $allvalue->mobile,
                'address' => $allvalue->address,
                'user_id' => $userinfo['user_id']
            );
            $addStatus = $this->DB->insert($this->db_name . 'user_address', $adderssdata, false);
        }
        if (empty($addStatus)) {
            $data = array(
                'status' => 403,
                'msg' => '地址保存失败',
            );
            echo json_encode($data);
            exit;
        } else {
            $data = array(
                'status' => 200,
                'msg' => '地址保存成功',
            );
            echo json_encode($data);
            exit;
        }

    }

    public function createOrder()
    {
        echo "暂停下单";
        die;
        $userinfo = $this->checkId();
        $paramsObj = json_decode($_GET['data']);
        if ($paramsObj->payPrice != 66) {
            $data = array(
                'status' => 404,
                'msg' => '参数错误',
            );
            echo json_encode($data);
            die;
        }
        if (empty($paramsObj->gid) or empty($paramsObj->number) or empty($paramsObj->sumPrice)) {
            $data = array(
                'status' => 404,
                'msg' => '没有商品信息',
            );
            echo json_encode($data);
            die;
        }
        $number = explode(",", $paramsObj->number);
        $godIDs = implode("','", explode(",", trim($paramsObj->gid, ',')));
        $goodsSql = "SELECT goods_id,goods_name,goods_sn,shop_price  FROM `" . $this->db_name . "goods` where goods_id IN ('{$godIDs}')";
        $goodsList = $this->DB->get_all($goodsSql);

        if (!$goodsList) {
            $data = array(
                'status' => 404,
                'msg' => '没有商品信息',
            );
            echo json_encode($data);
            die;
        }

        /* 计算商品总额 */
        $goods_amount = 0;
        foreach ($goodsList as $k => $good) {
            $goods['subtotal'] = $good['shop_price'] * $number[$k];
            $goods_amount += $goods['subtotal'];
        }

        $orderSn = $this->get_order_sn();
        $orderInfoData = array(
            'order_sn' => $orderSn,
            'user_id' => $userinfo['user_id'],
            'order_status' => 0,
            'shipping_status' => 0,
            'pay_status' => 0,
            'goods_amount' => $goods_amount,
            'shipping_fee' => 15,//TODO配送费用固定15
            'postscript' => null,
            'shipping_id' => '1',
            'shipping_name' => '随机快递',
            'consignee' => $paramsObj->consignee,
            'mobile' => $paramsObj->mobile,
            'consignee' => $paramsObj->address,
            'pay_id' => '1',
            'pay_name' => '微信支付',
            'order_amount' => $goods_amount + 15,//TODO配送费用固定15
            'referer' => '乐宠小程序',
            'add_time' => time(),
            'confirm_time' => time(),
            'extension_code' => '',
            'agency_id' => 0,
            'inv_type' => '',
            'tax' => 0,
            'discount' => 0,
            'best_time' => trim($params['best_time']),
            'add_time' => time(),
        );
        $addStatus = $this->DB->insert($this->db_name . 'order_info', $orderInfoData, false);
        if ($addStatus) {
            $orderId = $this->DB->insert_id();
        }

        /*插入订单商品详情*/
        foreach ($goodsList as $key => $value) {
            $gooddata = array(
                'order_id' => $orderId,
                'goods_id' => $value['goods_id'],
                'goods_name' => $value['goods_name'],
                'goods_sn' => $value['goods_sn'],
                'goods_price' => $value['shop_price'],
                'goods_number' => $number[$key]

            );
            $this->DB->insert($this->db_name . 'order_goods', $gooddata, false);
        }

        $money = $goods_amount + 15;
        $this->create_wechat_order($orderSn, $money);
        echo ok;


    }


    //微信支付
    public function create_wechat_order($orderSn, $money)
    {
        $payment_id = $orderSn;//订单单号
        $attach = "xcx";//附加信息

        $appid = "wx67ae3fa96e90c753";    /*微信开放平台上的应用id*/
        $mch_id = "1421430702";   /*微信申请成功之后邮件中的商户id*/
        $api_key = "leepet7190091421430702xxxxllllwr";    /*在微信商户平台上自己设定的api密钥 32位*/
        $notify_url = 'https://lechongwu.cn/plugins/API.v1.0/?a=shop&m=wechatCallback'; /*自定义的回调程序地址*/
        $body = "乐宠商品";
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $onoce_str = $this->getRandChar(32);
        $money = floatval($money) * 100;

        $data["appid"] = $appid;
        $data["body"] = $body;
        $data["mch_id"] = $mch_id;
        $data["nonce_str"] = $onoce_str;
        $data["notify_url"] = $notify_url;
        $data["out_trade_no"] = $payment_id;
        $data["attach"] = $attach;
        $data["spbill_create_ip"] = $this->get_client_ip(); //用户端实际ip
        $data["total_fee"] = $money;
        $data["trade_type"] = "JSAPI";
        $s = $this->getSign($data, false);
        $data["sign"] = $s;

        $xml = $this->arrayToXml($data);
        $response = $this->postXmlCurl($xml, $url);
        //将微信返回的结果xml转成数组
        $onedata = $this->xmlstr_to_array($response);
        print_r($response);
        print_r($onedata);
        die;
        if ($onedata['return_code'] == 'FAIL') {
            $smg = $onedata['return_msg'];
            $data = array(
                'status' => 404,
                'msg' => $smg
            );
            echo json_encode($data);
            die;
        }
        //二次签名
        $twodata = $this->getOrder($onedata['prepay_id'], $onedata['appid'], $onedata['mch_id']);
        if (empty($twodata)) {
            $smg = "二次签名获取失败";
            $data = array(
                'status' => 404,
                'msg' => $smg
            );
            echo json_encode($data);
            die;
        }
        $data = array('status' => '200', 'msg' => '微信下单接口调用成功', 'data' => $twodata);
        echo json_encode($data);
        die;


    }

    //
    #生成订单号
    private function get_order_sn()
    {

        mt_srand((double)microtime() * 1000000);

        return date('Ymdms') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    //获取指定长度的随机字符串
    function getRandChar($length)
    {
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol) - 1;

        for ($i = 0; $i < $length; $i++) {
            $str .= $strPol[rand(0, $max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }

        return $str;
    }

    /*
        获取当前服务器的IP
    */
    function get_client_ip()
    {
        if ($_SERVER['REMOTE_ADDR']) {
            $cip = $_SERVER['REMOTE_ADDR'];
        } elseif (getenv("REMOTE_ADDR")) {
            $cip = getenv("REMOTE_ADDR");
        } elseif (getenv("HTTP_CLIENT_IP")) {
            $cip = getenv("HTTP_CLIENT_IP");
        } else {
            $cip = "unknown";
        }
        return $cip;
    }

    /*
        生成签名
    */
    function getSign($Obj)
    {
        $api_key = "leepet7190091421430702xxxxllllwr";    /*在微信商户平台上自己设定的api密钥 32位*/
        foreach ($Obj as $k => $v) {
            $Parameters[strtolower($k)] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //echo "【string】 =".$String."</br>";
        //签名步骤二：在string后加入KEY
        $String = $String . "&key=" . $api_key;
        //echo "<textarea style='width: 50%; height: 150px;'>$String</textarea> <br />";
        //签名步骤三：MD5加密
        $result_ = strtoupper(md5($String));
        return $result_;
    }

    //将数组转成uri字符串
    function formatBizQueryParaMap($paraMap, $urlencode)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            $buff .= strtolower($k) . "=" . $v . "&";
        }
        $reqPar = '';
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }

    //数组转xml
    function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";

            } else
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
        }
        $xml .= "</xml>";
        return $xml;
    }

    //post https请求，CURLOPT_POSTFIELDS xml格式
    function postXmlCurl($xml, $url, $second = 30)
    {
        //初始化curl
        $ch = curl_init();
        //超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "curl出错，错误码:$error" . "<br>";
            echo "<a href='http://curl.haxx.se/libcurl/c/libcurl-errors.html'>错误原因查询</a></br>";
            curl_close($ch);
            return false;
        }
    }

    /**
     * xml转成数组
     */
    function xmlstr_to_array($xmlstr)
    {
        $doc = new DOMDocument();
        $doc->loadXML($xmlstr);
        return $this->domnode_to_array($doc->documentElement);
    }

    function domnode_to_array($node)
    {
        $output = array();
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
            case XML_TEXT_NODE:
                $output = trim($node->textContent);
                break;
            case XML_ELEMENT_NODE:
                for ($i = 0, $m = $node->childNodes->length; $i < $m; $i++) {
                    $child = $node->childNodes->item($i);
                    $v = $this->domnode_to_array($child);
                    if (isset($child->tagName)) {
                        $t = $child->tagName;
                        if (!isset($output[$t])) {
                            $output[$t] = array();
                        }
                        $output[$t][] = $v;
                    } elseif ($v) {
                        $output = (string)$v;
                    }
                }
                if (is_array($output)) {
                    if ($node->attributes->length) {
                        $a = array();
                        foreach ($node->attributes as $attrName => $attrNode) {
                            $a[$attrName] = (string)$attrNode->value;
                        }
                        $output['@attributes'] = $a;
                    }
                    foreach ($output as $t => $v) {
                        if (is_array($v) && count($v) == 1 && $t != '@attributes') {
                            $output[$t] = $v[0];
                        }
                    }
                }
                break;
        }
        return $output;
    }

    //执行第二次签名，才能返回给客户端使用
    public function getOrder($prepayId, $appid, $mch_id)
    {
        $data["appid"] = $appid;
        $data["noncestr"] = $this->getRandChar(32);;
        $data["package"] = "Sign=WXPay";
        $data["partnerid"] = $mch_id;
        $data["prepayid"] = $prepayId;
        $data["timestamp"] = time();
        $s = $this->getSign($data, false);
        $data["sign"] = $s;

        return $data;
    }

    //end 微信支付


    //字符串截取
    function substr_cut($str_cut, $length)
    {
        if (strlen($str_cut) > $length) {
            for ($i = 0; $i < $length; $i++)
                if (ord($str_cut[$i]) > 128) $i++;
            $str_cut = substr($str_cut, 0, $i) . "..";
        }
        return $str_cut;
    }


    public function test()
    {


        $key = $_GET['key'];
        $memcache = new Memcache;
        $memcache->connect('127.0.0.1', 11222) or die ("Could not connect");
//        $a = $memcache->set('test_item', 344);
//        $memcache->set('var_key', 'some really big variable', false, 50);
        echo $memcache->get($key);

// 显示 5

    }

    public function threadDetailNew($tid)
    {
        $tid = $_GET['tid'];
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=threadDetailNew&service=threadDetailNew&sign=73e38dcbf24327b06fba8b66b8bcf6c5&version=d1bd83a33f1a841ab7fda32449746cc4&tid=" . $tid;
        $data = $this->urlpost($url);
        echo $data;
    }

    public function actDetailByTageId($id)
    {
        $id = $_GET['id'];
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=actDetailByTageId&commtagType=1&page=1&pageSize=10&service=actDetailByTageId&sign=4691bd9fc2b9eea84d8a72953d226602&tagType=activityTag&version=d1bd83a33f1a841ab7fda32449746cc4&tagid=" . $id;
        $data = $this->urlpost($url);
        echo $data;
    }


    /**
     * 圈子列表
     * fid    2汪 3喵 14545572595其他
     */
    public function indexforumNew($fid)
    {
        //$fid =  $_GET['fid'];
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=indexforumNew&service=indexforumNew&sign=b4c4e12895a075d5cc4f7f42120aa7d4&uid=1447957&version=d1bd83a33f1a841ab7fda32449746cc4&fid=2";
        $data = $this->urlpost($url);
        $url_m = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=indexforumNew&service=indexforumNew&sign=b4c4e12895a075d5cc4f7f42120aa7d4&uid=1447957&version=d1bd83a33f1a841ab7fda32449746cc4&fid=3";
        $data_m = $this->urlpost($url_m);
        $url_q = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=indexforumNew&service=indexforumNew&sign=b4c4e12895a075d5cc4f7f42120aa7d4&uid=1447957&version=d1bd83a33f1a841ab7fda32449746cc4&fid=14545572595";
        $data_q = $this->urlpost($url_q);

        $w = json_decode($data)->array;
        $m = json_decode($data_m)->array;
        $q = json_decode($data_q)->array;
        $forum = array(
            'w' => $w,
            'm' => $m,
            'q' => $q,
        );
        echo json_encode($forum);
    }

    /**
     * 具体圈子内容贴列表
     *tagid --》id具体圈子
     */
    public function actDetailByTageIdV4($id)
    {
        $id = $_GET['id'];
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=actDetailByTageIdV4&filter=&page=1&pagesize=10&service=actDetailByTageIdV4&sign=988b0be7278c2a7d0e6102920e24a565&tagType=forumTag&version=d1bd83a33f1a841ab7fda32449746cc4&tagid=" . $id;
        $data = $this->urlpost($url);
        echo $data;
    }

    /**
     * 具体圈子置顶帖
     *fid --》id具体圈子
     */
    public function forumThreadTopList($id)
    {
        $id = $_GET['id'];
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=forumThreadTopList&service=forumThreadTopList&sign=236bfb62154b751267263256919073c6&version=d1bd83a33f1a841ab7fda32449746cc4&fid=" . $id;
        $data = $this->urlpost($url);
        echo $data;
    }
    #TOO 获取参数类型。


}