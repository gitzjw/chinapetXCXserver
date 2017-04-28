<?php

class ERP
{

    public $DB;

    public $erp_path;

    public function __construct()
    {
        if ($this->decryption($_REQUEST['sign'])) {
            printJson(0, '', "验证未通过！");
        }
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

    #name ：获取订单状态
    #description : 通过ERP，获取订单状态
    public function orderStatus()
    {
        $orderID = $_REQUEST['orderID'] ? $_REQUEST['orderID'] : printJson(0, '', '参数错误！');
        $checkOrderStatus = "SELECT order_id, order_sn FROM `ecs_order_info` WHERE pay_status=2 AND order_id={$orderID}";
        $checkResult = $this->DB->get_one($checkOrderStatus);

        if (!$checkResult || $checkResult['order_id'] < 0) {
            printJson(0, '', '订单还未支付！');
        }
        $url = $this->erp_path . "&action=getoneorderstatus&orderno=" . $checkResult['order_sn'];
        echo $url;
//		$url = "http://180.168.83.222:8087/GetData.aspx?action=getoneorderstatus&key=lee&orderno=".$checkResult['order_sn'];
        $orderStatus = @file_get_contents($url);
        $orderStatus = json_decode($orderStatus, TRUE);

        if ($orderStatus && !empty($orderStatus)) {
            $shippingStatus['shipping_status'] = '1';
            try {
                foreach ($orderStatus as $key => $val) {
                    $sn_arr = explode(",", $val['lydh']);
                }
                foreach ($sn_arr as $k => $v) {
                    // $updateResult[] = $this->DB->update('ecs_order_info', $shippingStatus, "order_sn='{$checkResult['order_sn']}'");
                    $updateResult[] = $this->DB->update('ecs_order_info', $shippingStatus, "order_sn='{$v}'");
                }

            } catch (Exception $e) {
                $this->log(json_encode(array("params" => $shippingStatus, "message" => $e->getMessage())));
            }
            printJson(1, array('order_sn' => $orderStatus[0]['lydh']), '');
        }
        printJson(1, '', '订单还未发货！');
    }

    #获取全部订单状态
    public function allOrderStatus()
    {
//		$url = "http://180.168.83.222:8087/GetData.aspx?action=getorderstatus&key=lee";
        $url = $this->erp_path . "&action=getorderstatus";
        $order_status = file_get_contents($url);
        echo $order_status;
//        echo "<pre>";
//        var_dump($order_status);
    }



    #name : 推送支付过的订单  2017-1-18 （转移到orderbasemodel中）
    #description ：向ERP系统推送支付过的订单  by jw
    public function pushPayOrder()
    {
        $params = checkParam(array('order_sn'), $_REQUEST);
        if (!$params) {
            printJson(0, '', '参数错误！');
        }
        $orderId = $orderSn = 0;
        //, 'shipping_fee', 'shipping', 'uname', 'address', 'phone', 'province', 'city', 'area', 'order_amount', 'order_weight', 'buyer_comment'
        $orderSql = "SELECT order_id,order_sn,user_id,pay_note,money_paid,consignee,province,city,district area,address,mobile,postscript,shipping_name,shipping_fee,order_amount,pay_time,add_time FROM `" . $this->db_name . "order_info` WHERE order_sn={$params['order_sn']}";
        $orderInfo = $this->DB->get_one($orderSql);

        if (!$orderInfo) {
            #TODO 失败后继续请求 3次
            return false;
        }
        $orderId = $orderInfo['order_id'];
        $orderSn = $orderInfo['order_sn'];
        $areaInfo = $this->getAreaInfo($orderInfo['province'], $orderInfo['city'], $orderInfo['area']);
        $goodsSql = "SELECT g.goods_weight weight,g.goods_sn,og.goods_number,og.goods_price,og.product_id FROM `" . $this->db_name . "order_goods` og LEFT JOIN `" . $this->db_name . "goods` g ON og.goods_id=g.goods_id WHERE og.order_id={$orderInfo['order_id']}";
        $goodsInfo = $this->DB->get_all($goodsSql);
        $goodsWeight = $goodsNumber = 0;

        $rowInfo = array();
        foreach ($goodsInfo as $key => $val) {
            $goodsTm = $this->getGoodsTmByProductId($val['product_id']);
            $rowInfo[$key]['tm'] = $goodsTm ? $goodsTm : $val['goods_sn'];
            $rowInfo[$key]['sl'] = $val['goods_number'];
            $rowInfo[$key]['sjdj'] = $val['goods_price'];
            $goodsWeight = $goodsWeight + $val['weight'];
            $goodsNumber = $goodsNumber + $val['goods_number'];
        }
//		$url = "http://180.168.83.222:8087/GetData.aspx?action=createsalesorder&key=lee";
        $url = $this->erp_path . "&action=createsalesorder";

        $header = "Content-type: text/html;charset=utf-8";
        $orderData = array(
            'lydh' => "WX:" . $orderInfo['order_sn'],
            'wlfy' => $orderInfo['shipping_fee'],
            'wlgs' => $orderInfo['shipping_name'],
            'rq' => date("Y-m-d H:i:s", $orderInfo['add_time']),
            'shr' => $orderInfo['consignee'],
            'dizhi' => $areaInfo['province']['region_name'] . $areaInfo['city']['region_name'] . $areaInfo['area']['region_name'] . $orderInfo['address'],
            'shouji' => $orderInfo['mobile'],
            'sheng' => $areaInfo['province']['region_name'] . "省",
            'shi' => $areaInfo['city']['region_name'] . "市",
            'qu' => $areaInfo['area']['region_name'],
            'zje' => $orderInfo['money_paid'],
            'zzl' => $goodsWeight,
            'bz' => "wx:" . $orderInfo['postscript'],
            'mjbz' => $orderInfo['postscript'],
            'rows' => $rowInfo,
            'zsl' => $goodsNumber,
            'zfrq' => date("Y-m-d H:i:s", $orderInfo['pay_time']),
            'jyh' => $orderInfo['pay_note'],
        );
        if ($_REQUEST['debug'] == 2) {
            var_dump($orderData);
            die;
        }
        #获取用户信息
        $UserSql = "SELECT user_name, user_id FROM `" . $this->db_name . "users` WHERE user_id={$orderInfo['user_id']}";
        $userInfo = $this->DB->get_one($UserSql);
        $orderData['hydm'] = $userInfo['user_id'];
        $orderData['hymc'] = $userInfo['user_name'];

//		print_r($orderData);die;

        $orderInfo = "inputdata=" . json_encode($orderData);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $orderInfo);
        $response = curl_exec($ch);
        curl_close($ch);

        $this->log("URL : " . $url, "url");
        $this->log("参数 : " . json_encode($orderInfo), "url");
        $this->log("返回 ：" . $response, "url");

        $this->log("推送结果纪录：" . $response, "auto_push_order_logs");

        $res = json_decode($response, true);
        $insertData = array(
            'order_sn' => $params['order_sn'],
            'pushtime' => time(),
        );

        if ((int)$res['code'] == 1) {
            $this->log(json_encode(array('time' => date("Y-m-d", time()), 'param' => $orderData)), "push-order-success");
            $insertData['status'] = 1;
            $insertData['message'] = '推送成功';
            #插入数据库
            $this->DB->insert($this->db_name . "erp_order_push_log", $insertData);
            #插入自动更新纪录
            $this->consolePushOrder($orderId, $orderSn);
            printJson(1, '', '推送成功！');
        }
        $this->log(json_encode(array('time' => date("Y-m-d", time()), 'param' => $orderData, 'message' => $res['message'])), "push-order-fial");
        $insertData['status'] = 2;
        $insertData['message'] = $res['message'];
        #插入数据库
        $this->DB->insert($this->db_name . 'erp_order_push_log', $insertData);
        printJson(0, '', $res['message']);
    }

    #纪录一推送成功的订单，用以定时刷新拿发货物流状态用
    private function consolePushOrder($order_id, $order_sn)
    {
        #检测订单信息
        $orderInfo = $this->DB->get_one("SELECT order_id, order_status, pay_status, shipping_status,user_id FROM `" . $this->db_name . "order_info` WHERE order_id='{$order_id}' AND order_sn='{$order_sn}'");
        if ((int)$orderInfo['order_id'] < 1) {
            return false;
        }
        #检测订单状态
        if ($orderInfo['pay_status'] != 2) {
            return false;
        }
        #检测订单是否存在
        $order = $this->DB->get_one("SELECT * FROM `" . $this->db_name . "erp_auto_push` WHERE order_id='{$order_id}' AND order_sn='{$order_sn}'");
        if ($order && (int)$order['order_id'] > 0) {
            return true;
        }
        $insertSource = array(
            'order_id' => $order_id,
            'order_sn' => $order_sn,
            'addtime' => time(),
            'uid' => $orderInfo['user_id'],
        );
        #插入数据表
        $addResult = $this->DB->insert($this->db_name . "erp_auto_push", $insertSource);
        return true;
    }

    #定时器 定时去拿订单状态 （有效 by jw 设定是脚本 2017-1-18）
    public function autoRunOrderStatus()
    {
        $this->log("定时获取订单状态：运行时间－>:" . date("Y-m-d H:i:s"), "auto-run-order");

        $waitRunOrderInfo = $this->DB->get_all("SELECT * FROM  `" . $this->db_name . "erp_auto_push` WHERE 1");
        if (count($waitRunOrderInfo) < 1) {
            printJson(1, '', 'success');
        }

        foreach ($waitRunOrderInfo as $key => $val) {
            $order_sql = "SELECT order_id,consignee FROM  `" . $this->db_name . "order_info` WHERE order_sn = {$val['order_sn']}";
            $OrderInfo = $this->DB->get_one($order_sql);
            if (empty($OrderInfo)) {
                continue;
            }
            #订单推送设置 0 不推送 1推送已发货 2推送已在路上
            $shippingStatus = 0;
			//$url = "http://180.168.83.222:8087/GetData.aspx?action=getoneorderstatus&key=lee&orderno=".$val['order_sn'];
            //$orderStatus = @file_get_contents($url);
            $url = $this->erp_path . "&action=getoneorderstatus&orderno=WX" . $val['order_sn'];
            $orderStatus = $this->curlPush($url);


            $this->log("定时获取订单状态：运行ERP结果－>:" . $orderStatus . '[' . $val['order_sn'] . '[url:' . $url, "auto-run-order");
            $orderStatus = json_decode($orderStatus, TRUE);

            #erp已审单 商城处理已发货 order_status  1是审核 0是未审核  shipping_status  1 是发货
            if ((int)$orderStatus[0]['order_status'] == 1) {

                $shippingStatus = 1;
                $conditon = "WHERE order_id='{$val['order_id']}'";
                $updateSql = "UPDATE `" . $this->db_name . "order_info` SET shipping_status=1 ";
                if ((int)$orderStatus[0]['shipping_status'] == 1) {
                    $shippingStatus = 2;
                    if ($orderStatus[0]['shipping_no']) {
                        $updateSql .= ",invoice_no='" . trim($orderStatus[0]['shipping_no']) . "'";
                    }
                    if ($orderStatus[0]['shipping_comp']) {
                        $updateSql .= ",shipping_name='" . trim($orderStatus[0]['shipping_comp']) . "'";
                    }
                }
                $this->log("定时获取订单状态：待修改订单数据－>:" . $updateSql, "auto-run-order");

                $sqlzz[$key] = $updateSql;
                #更改订单状态为已发货
                $this->DB->query($updateSql . $conditon);
                if ($shippingStatus > 1) {
                    #删掉本条纪录
                    $this->DB->query("DELETE FROM `" . $this->db_name . "erp_auto_push` WHERE pushid='{$val['pushid']}'");
                }

                /* 微信通模板消息之发货通知  ERP回执 start by jw */
                $file = $_SERVER['DOCUMENT_ROOT'] . '/include/apps/default/controllers/WechatController.class.php';
                $file_mobile = $_SERVER['DOCUMENT_ROOT'] . '/mobile/include/apps/default/controllers/WechatController.class.php';
                if (file_exists($file)) {
                    // 独立版
                    $file_final = $file;
                    $mobile_url = $_SERVER['HTTP_HOST'];//$GLOBALS['ecs']->url();
                } elseif (file_exists($file_mobile)) {
                    // 整合版
                    $file_final = $file_mobile;
                    $mobile_url = $_SERVER['HTTP_HOST'] . '/mobile';//$GLOBALS['ecs']->url() . 'mobile/';
                } else {
                    $file_final = '';
                }

                if (file_exists($file_final) && $val['uid'] > 0) {

                    $pushData = array(
                        'first' => array('value' => '您的订单已经发货', 'color' => '#173177'), //提示
                        'keyword1' => array('value' => $val['order_sn']), //订单内容
                        'keyword2' => array('value' => $orderStatus[0]['shipping_comp']), //物流服务
                        'keyword3' => array('value' => $orderStatus[0]['shipping_no']),  //快递单号
                        'keyword4' => array('value' => $OrderInfo['consignee']),  // 收货信息
                        'remark' => array('value' => '正在配送中,请您留意物流信息', 'color' => '#173177')
                    );

                    $code = 'OPENTM202243318';
                    $order_url = $mobile_url . '/index.php?c=user&a=order_detail&order_id=' . $val['order_id'];

                    $order_url = urlencode(base64_encode($order_url));
                    //以json格式传输
                    $data = urlencode(serialize($pushData));
                    $url = $mobile_url . '/index.php?c=api&a=index&user_id=' . $val['uid'] . '&code=' . urlencode($code) . '&pushData=' . $data . '&url=' . $order_url;

                    $this->curlGet($url);

                }
                /* 微信通模板消息之发货通知 end by ectouch */

                /* 清除缓存 */
                clear_cache_files();
            }
        }
        printJson(1, '', 'success');
    }

    /*
     * curl*/
    function curlPush($url, $orderInfo)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $orderInfo);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }


    /**
     * curl 获取
     */
    function curlGet($url, $timeout = 5, $header = "")
    {
        $defaultHeader = '$header = "User-Agent:Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12\r\n";
        $header.="Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n";
        $header.="Accept-language: zh-cn,zh;q=0.5\r\n";
        $header.="Accept-Charset: utf-8;q=0.7,*;q=0.7\r\n";';
        $header = empty($header) ? $defaultHeader : $header;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);    // https请求 不验证证书和hosts
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array($header)); //模拟的header头
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


    /**
     * 得到新发货单号
     * @return  string
     */
    function get_delivery_sn()
    {
        /* 选择一个随机的方案 */
        mt_srand((double)microtime() * 1000000);

        return date('YmdHi') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    #拿回执erp信息生成简单的发货单用以查询物流信息（暂时没有使用）
    public function inset_delivery_order($shipping_no, $shipping_comp, $order_id, $order_sn, $user_id)
    {
        if (empty($shipping_no)) {
            return false;
        };
        if (empty($shipping_comp)) {
            return false;
        }
        $_delivery = array();
        /*发货单号*/
        $delivery['delivery_sn'] = $this->get_delivery_sn();
        $delivery_sn = $delivery['delivery_sn'];
        $add_time = time();
        /* 获取发货单所属供应商 */
        $delivery['suppliers_id'] = '';
        /* 设置默认值 */
        $delivery['status'] = 2; // 正常
        $delivery['order_id'] = $order_id;
        $delivery['order_sn'] = $order_sn;
        $delivery['invoice_no'] = $shipping_no;
        $delivery['add_time'] = $add_time;
        $delivery['update_time'] = $add_time;
        $delivery['shipping_id'] = 1;
        $delivery['shipping_name'] = $shipping_comp;
        $delivery['user_id'] = $user_id;
        $delivery['action_user'] = 'ErpAdmin';
        $delivery['consignee'] = '99';
        $delivery['address'] = '88';

        #插入数据库
        $this->DB->insert($this->db_name . 'delivery_order', $delivery);
    }


    #name :给用户发送发货推送
    private function pushToUser($uid, $type, $orderSn)
    {
        $alert = "您的尾巴圈乐购订单【" . $orderSn . "】已发货";
        if ($type == 2) {
            $alert = "您的尾巴圈乐购订单 【" . $orderSn . "】可以查看物流信息啦";
        }
        $notifyUrl = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=shopMessagePush&uid=" . $uid . "&alert=" . $alert . "&version=d1bd83a33f1a841ab7fda32449746cc4";
        file_get_contents($notifyUrl);
        return true;
    }

    #name ：同步商品库存
    #description ： 通过ERP系统，同步商品库存
    public function syncGoodsNumber()
    {
//		$url = "http://180.168.83.222:8087/GetData.aspx?action=getmasterdata&key=lee";
        $url = $this->erp_path . "&action=getmasterdata";
        $goodsInfo = @file_get_contents($url);
        $goodsInfo = json_decode($goodsInfo, TRUE);
        $goodsNumber = array();
        if ($goodsInfo) {
            foreach ($goodsInfo as $key => $val) {
                $updateData['goods_number'] = (int)$val['kc'];
                $goodsNumber[] = (int)$val['kc'];
                try {
                    $updateResult[] = $this->DB->update('ecs_goods', $updateData, "goods_sn='{$val['sptm']}'");
                } catch (Exception $e) {
                    $this->log(json_encode(array("params" => $updateData, "message" => $e->getMessage())));
                }
            }
            printJson(1, array('goods_number' => $goodsNumber), '');
        }
    }

    /*public function syncNumber() {
        $url = "http://180.168.83.222:8087/GetData.aspx?action=getmasterdata&key=lee";
        $goodsInfos = @file_get_contents($url);
        $goodsInfos = json_decode($goodsInfos, TRUE);
        $goodsNumber = array();
        if($goodsInfos) {
            #清除所有库存
            $delNumberSql = "UPDATE `ecs_goods` SET goods_number = 0";
            $this->DB->query($delNumberSql);

            foreach ($goodsInfos as $key => $val) {
                #根据商品编码查出商品id
                if($val['spdm']) {
                    $goodsInfoSql = "SELECT goods_id,goods_number FROM `ecs_goods` WHERE goods_sn='{$val['spdm']}'";
                    $goodsInfo = $this->DB->get_one($goodsInfoSql);
                } else {
                    $goodsInfo['goods_id'] = 0;
                }
                #根据商品id和属性 查出属性id
                $attrNames = "";
                if($val['propertiesname'] != '' && $goodsInfo['goods_id'] > 0) {
                    $propInfo = explode(';', $val['propertiesname']);
                    foreach ($propInfo as $keys => $vals) {
                        $prop = explode(":", $vals);
                        $attribute[$prop[2]][$prop[0]] = $prop[2];
                        $attribute[$prop[2]][$prop[1]] = $prop[3];
                        if($prop[3]) {
                            $attrNames .= "'".$prop[3]."',";
                        }
                    }
                } else {
                    #直接更新goods表库存
                    $updateData['goods_number'] = (int)$val['kc'] > 0 ? (int)$val['kc'] : 0;
                    try {
                        $updateResult[] = $this->DB->update('ecs_goods', $updateData, "goods_sn='{$val['spdm']}'");
                    } catch (Exception $e){
                        $this->log(json_encode(array("params" => $updateData, "message" => $e->getMessage())));
                    }
                    //printJson(1, '', '更新成功！');
                    continue;
                }

                $attrNames = trim($attrNames, ',');
                $this->log($attrNames, "attr_name");
                $attrInfoSql = "SELECT goods_attr_id,goods_id FROM `ecs_goods_attr` WHERE goods_id={$goodsInfo['goods_id']} AND attr_value IN ({$attrNames})";
                $attrInfo = $this->DB->get_all($attrInfoSql);
                if($attrInfo){
                    $count = count($attrInfo);
                    $attrIDsFirst = $attrIDsSecond = '';
                    for($i = 0; $i < $count; $i++) {
                        $attrIDsFirst .= $attrInfo[$i]['goods_attr_id'] . '|';
                    }
                    for($i = $count-1; $i >= 0; $i--) {
                        $attrIDsSecond .= $attrInfo[$i]['goods_attr_id'] . '|';
                    }
                    $attrIDsFirst = trim($attrIDsFirst, '|');
                    $attrIDsSecond = trim($attrIDsSecond, '|');
                }
                #根据属性id和商品id查出库存 更新库存,并且商品表中库存跟着变化
                $proInfoSql = "SELECT product_number, product_id FROM `ecs_products` WHERE goods_id={$goodsInfo['goods_id']} AND goods_attr = '{$attrIDsFirst}'";
                $proInfo = $this->DB->get_one($proInfoSql);
                if(!$proInfo) {
                    $proInfoSqls = "SELECT product_number, product_id FROM `ecs_products` WHERE goods_id={$goodsInfo['goods_id']} AND goods_attr = '{$attrIDsSecond}'";
                    $proInfo = $this->DB->get_one($proInfoSqls);
                }
                if($proInfo) {
                    if($val['kc'] != $proInfo['product_number']) {
                        $number = (int)$val['kc'] - (int)$proInfo['product_number'];
                        $val['kc'] = (int)$val['kc'] > 0 ? (int)$val['kc'] : 0;
                        $updateProRe = $this->DB->update('ecs_products', array('product_number' => $val['kc']), "product_id={$proInfo['product_id']}");
                    }
                }
            }
            $goodsIDSql = "SELECT goods_id FROM `ecs_products` WHERE 1 GROUP BY goods_id";
            $goodsIDS = $this->DB->get_all($goodsIDSql);
            if(!$goodsIDS) {
                return false;
            }
            foreach($goodsIDS as $IDK => $IDV) {
                $allNumberSql = "SELECT SUM(product_number) product_number FROM `ecs_products` WHERE goods_id={$IDV['goods_id']}";
                $allNmber = $this->DB->get_one($allNumberSql);
                $updateGoodsNumberResult = $this->DB->update('ecs_goods', array('goods_number' => $allNmber['product_number']), "goods_id={$IDV['goods_id']}");
            }
            printJson(1, '', '更新成功！');
        }
    }*/

    #name ：同步商品
    #description ：通过ERP，同步商品到库里
    public function syncGoods()
    {
//		$url = "http://180.168.83.222:8087/GetData.aspx?action=getmasterdata&key=lee";
        $url = $this->erp_path . "&action=getmasterdata";
        $goodsInfo = @file_get_contents($url);
        $goodsInfo = json_decode($goodsInfo, TRUE);
        if ($goodsInfo) {
            #清除所有库存
            $delNumberSql = "UPDATE `ecs_goods` SET goods_number = 0";
            $this->DB->query($delNumberSql);
            foreach ($goodsInfo as $key => $val) {
                $goodsGallery = explode(';', trim($val['tupian'], ';'));
                $goods = array(
                    'cat_id' => 17,
                    'goods_sn' => trim($val['spdm']),
                    'goods_name' => str_replace("'", "‘", trim($val['spmc'])),
                    'click_count' => 0,
                    'brand_id' => $this->syncBrand($val['pinpai']),
                    'goods_number' => $val['kc'] < 0 ? 0 : $val['kc'],
                    'goods_weight' => $val['zl'] ? $val['zl'] : 0,
                    'market_price' => $val['price'] * 1.2,
                    'shop_price' => $val['price'],
                    'warn_number' => 1,
                    'keywords' => '',
                    'goods_desc' => trim($val['goods_desc']),
                    'goods_thumb' => $goodsGallery[0] ? $goodsGallery[0] : '',
                    'is_real' => 1,
                    'is_on_sale' => 1,
                    'is_alone_sale' => 1,
                    'add_time' => time(),
                    'sort_order' => 100,
                    'is_delete' => 0,
                    'last_update' => time(),
                    'goods_type' => 0,
                    'integral' => 0,
                    'is_best' => 0,
                    'is_new' => 0,
                    'is_hot' => 0,
                    'is_promote' => 0,
                );
                #TODO insert into or update goods table
                $checkGoodsSql = "SELECT goods_id,goods_number,shop_price FROM `ecs_goods` WHERE goods_sn = '{$val['spdm']}'";
                $checkResult = $this->DB->get_one($checkGoodsSql);
                if ($checkResult && $checkResult['goods_id'] > 0) {
                    try {
                        #$goodsNumbers = $this->getGoodsNumber($checkResult['goods_id']);
                        $goods['goods_number'] = (int)$goods['goods_number'] + (int)$checkResult['goods_number'];
                        if ((int)$checkResult['shop_price'] <= (int)$goods['shop_price']) {
                            unset($goods['shop_price']);
                        }
                        unset($goods['cat_id']);
                        $updateResult = $this->DB->update('ecs_goods', $goods, "goods_id=" . $checkResult['goods_id']);
                        //$this->addAttribute($val['skumc'], $val['sptm'], $checkResult['goods_id'], $val['price'], $val['kc']);
                        $this->syncGoodsAttr($val, $checkResult['goods_id']);
                    } catch (Exception $e) {
                        $this->log(json_encode(array("params" => $goods, "message" => $e->getMessage())));
                    }
                    $goodsIDs[] = $checkResult['goods_id'];
                    #更新商品相册
                    $addRe = $this->syncGoodsGallry($checkResult['goods_id'], $goodsGallery, true);
                } else {
                    try {
                        $insertResult = $this->DB->insert('ecs_goods', $goods);
                        $goodsID = $this->DB->insert_id();
                        //$this->addAttribute($val['skumc'], $val['sptm'], $goodsID, $val['price'], $val['kc']);
                        $this->syncGoodsAttr($val, $goodsID);
                    } catch (Exception $e) {
                        $this->log(json_encode(array("params" => $goods, "message" => $e->getMessage())));
                    }
                    $goodsIDs[] = $goodsID;
                    if (!$goodsID) {
                        $this->log(json_encode(array("params" => $goods, "message" => "goods id get fiald")));
                    }
                    #商品相册
                    $addRe = $this->syncGoodsGallry($goodsID, $goodsGallery);
                }
            }
            $goodsIDSql = "SELECT goods_id FROM `ecs_products` WHERE 1 GROUP BY goods_id";
            $goodsIDS = $this->DB->get_all($goodsIDSql);
            if (!$goodsIDS) {
                return false;
            }
            foreach ($goodsIDS as $IDK => $IDV) {
                $allNumberSql = "SELECT SUM(product_number) product_number FROM `ecs_products` WHERE goods_id={$IDV['goods_id']}";
                $allNmber = $this->DB->get_one($allNumberSql);
                $updateGoodsNumberResult = $this->DB->update('ecs_goods', array('goods_number' => $allNmber['product_number']), "goods_id={$IDV['goods_id']}");
            }
            printJson(1, array('goods_id' => $goodsIDs), '');
        }
    }

    #跟据商品名称，更改商品条形码
    public function syncGoodsSpdm()
    {
//		$url = "http://180.168.83.222:8087/GetData.aspx?action=getmasterdata&key=lee";
        $url = $this->erp_path . "&action=getmasterdata";
        $goodsInfo = @file_get_contents($url);
        $goodsInfo = json_decode($goodsInfo, TRUE);
        $updateResult = array();
        if ($goodsInfo) {
            foreach ($goodsInfo as $key => &$val) {
                $val['spmc'] = addslashes($val['spmc']);
                $checkGoodsSql = "SELECT goods_id,goods_number,shop_price FROM `ecs_goods` WHERE goods_sn = '{$val['spdm']}'";
                $checkResult = $this->DB->get_one($checkGoodsSql);
                if ($checkResult && $checkResult['goods_id'] > 0) {
                    continue;
                } else {
                    $checkGoodsSqls = "SELECT goods_id,goods_number,shop_price FROM `ecs_goods` WHERE goods_name like '%{$val['spmc']}%'";
                    $checkResults = $this->DB->get_one($checkGoodsSqls);
                    if ($checkResults && $checkResults['goods_id'] > 0) {
                        $updateResult[] = $this->DB->update('ecs_goods', array('goods_sn' => trim($val['spdm'])), "goods_name like '%{$val['spmc']}%'");
                    }
                }
            }
        }
        var_dump($updateResult);
    }
    #name ：同步商品
    #description ：通过ERP，同步商品到库里
    public function syncGoodsBak()
    {
//		$url = "http://180.168.83.222:8087/GetData.aspx?action=getmasterdata&key=lee";
        $url = $this->erp_path . "&action=getmasterdata";
        $goodsInfo = @file_get_contents($url);
        $goodsInfo = json_decode($goodsInfo, TRUE);
        if ($goodsInfo) {
            foreach ($goodsInfo as $key => $val) {
                $goodsGallery = explode(';', trim($val['tupian'], ';'));
                $goods = array(
                    'cat_id' => 17,
                    'goods_sn' => trim($val['spdm']),
                    'goods_name' => str_replace("'", "‘", trim($val['spmc'])),
                    'click_count' => 0,
                    'brand_id' => $this->syncBrand($val['pinpai']),
                    'goods_number' => $val['kc'] < 0 ? 0 : $val['kc'],
                    'goods_weight' => $val['zl'] ? $val['zl'] : 0,
                    'market_price' => $val['price'] * 1.2,
                    'shop_price' => $val['price'],
                    'warn_number' => 1,
                    'keywords' => '',
                    'goods_desc' => trim($val['goods_desc']),
                    'goods_thumb' => $goodsGallery[0] ? $goodsGallery[0] : '',
                    'is_real' => 1,
                    'is_on_sale' => 1,
                    'is_alone_sale' => 1,
                    'add_time' => time(),
                    'sort_order' => 100,
                    'is_delete' => 0,
                    'last_update' => time(),
                    'goods_type' => 0,
                    'integral' => 0,
                    'is_best' => 0,
                    'is_new' => 0,
                    'is_hot' => 0,
                    'is_promote' => 0,
                );
                #TODO insert into or update goods table
                #$checkGoodsSql = "SELECT goods_id,goods_number,shop_price FROM `ecs_goods` WHERE goods_sn = '{$val['spdm']}'";
                $checkGoodsSql = "SELECT goods_id FROM `ecs_products` WHERE product_sn = '{$val['sptm']}'";
                $checkResult = $this->DB->get_one($checkGoodsSql);
                if ($checkResult && $checkResult['goods_id'] > 0) {
                    continue;
                } else {
                    try {
                        $insertResult = $this->DB->insert('ecs_goods', $goods);
                        $goodsID = $this->DB->insert_id();
                        //$this->addAttribute($val['skumc'], $val['sptm'], $goodsID, $val['price'], $val['kc']);
                        $this->syncGoodsAttr($val, $goodsID);
                    } catch (Exception $e) {
                        $this->log(json_encode(array("params" => $goods, "message" => $e->getMessage())));
                    }
                    $goodsIDs[] = $goodsID;
                    if (!$goodsID) {
                        $this->log(json_encode(array("params" => $goods, "message" => "goods id get fiald")));
                    }
                    #商品相册
                    $addRe = $this->syncGoodsGallry($goodsID, $goodsGallery);
                }
            }
            if (!$goodsIDs) {
                printJson(0, array(), '暂无需要同步的商品！');
            }
            foreach ($goodsIDs as $IDK => $IDV) {
                $allNumberSql = "SELECT SUM(product_number) product_number FROM `ecs_products` WHERE goods_id={$IDV}";
                $allNmber = $this->DB->get_one($allNumberSql);
                $updateGoodsNumberResult = $this->DB->update('ecs_goods', array('goods_number' => $allNmber['product_number']), "goods_id={$IDV}");
            }
            printJson(1, array('goods_id' => $goodsIDs), '');
        }
        printJson(0, array(), '暂无需要同步的商品！');
    }

    #同步商品，通过条码来判断（最新）
    public function SYNCGoodsByTm()
    {
        set_time_limit(0);
        $params = checkParam(array('t'), $_REQUEST);
        if (!$params) {
            printJson(0, '', '参数错误！');
        }
        if ($_REQUEST['t'] == 'c') {
            #炊烟
//	     	$url = "http://180.168.83.222:8087/GetData.aspx?action=getmasterdata_cy&key=lee";
            $url = $this->erp_path . "&action=getmasterdata_cy";
        } else if ($_REQUEST['t'] == 'q') {
            #Q仔
//	    	$url = "http://180.168.83.222:8087/GetData.aspx?action=getmasterdata_qzai&key=lee";
            $url = $this->erp_path . "&action=getmasterdata_qzai";
        } else {
            printJson(0, '', '参数错误！');

        }
        #炊烟
        #$url = "http://180.168.83.222:8087/GetData.aspx?action=getmasterdata_cy&key=lee";
        #Q仔
        #$url = "http://180.168.83.222:8087/GetData.aspx?action=getmasterdata_qzai&key=lee";
        $goodsInfo = @file_get_contents($url);
        $goodsInfo = json_decode($goodsInfo, TRUE);
        #var_dump($goodsInfo);die;
        if ($goodsInfo) {
            foreach ($goodsInfo as $key => $val) {
                $goodsGallery = explode(';', trim($val['tupian'], ';'));
                $goods = array(
                    'cat_id' => 3,
                    'goods_sn' => trim($val['spdm']),
                    'goods_name' => str_replace("'", "‘", trim($val['spmc'])),
                    'click_count' => 0,
                    'brand_id' => $this->syncBrand($val['pinpai']),
                    'goods_number' => $val['kc'] < 0 ? 0 : $val['kc'],
                    'goods_weight' => $val['zl'] ? $val['zl'] : 0,
                    'market_price' => $val['price'] * 1.2,
                    'shop_price' => $val['price'],
                    'warn_number' => 1,
                    'keywords' => '',
                    'goods_desc' => trim($val['goods_desc']),
                    'goods_thumb' => $goodsGallery[0] ? $goodsGallery[0] : '',
                    'is_real' => 1,
                    'is_on_sale' => 0,
                    'is_alone_sale' => 1,
                    'add_time' => time(),
                    'sort_order' => 100,
                    'is_delete' => 0,
                    'last_update' => time(),
                    'goods_type' => 0,
                    'integral' => 0,
                    'is_best' => 0,
                    'is_new' => 0,
                    'is_hot' => 0,
                    'is_promote' => 0,
                );
                #var_dump($goods);die;
                #根据条码来判断商品是否存在，存在的话直接跳过；不存在的话，创建商品
                #1.判断条码是否存在
                $checkGoodsSql = "SELECT goods_id FROM `ecs_products` WHERE product_sn = '{$val['sptm']}'";
                $checkResult = $this->DB->get_one($checkGoodsSql);
                if ($checkResult && $checkResult['goods_id'] > 0) {
                    continue;
                } else {
                    #TODO insert into or update goods table
                    $checkGoodsSql = "SELECT goods_id,goods_number,shop_price FROM `ecs_goods` WHERE goods_sn = '{$val['spdm']}'";
                    $checkResults = $this->DB->get_one($checkGoodsSql);
                    if ($checkResults && $checkResults['goods_id'] > 0) {
                        #update
                        $price = $checkResults['shop_price'] > $val['price'] ? $val['price'] : $checkResults['shop_price'];
                        $number = (int)$checkResults['goods_number'] + (int)$val['kc'];
                        try {
                            $updateResult = $this->DB->update('ecs_goods', array("shop_price" => $price, 'goods_number' => $number), "goods_id = {$checkResults['goods_id']}");
                            $this->syncGoodsAttr($val, $checkResults['goods_id']);
                        } catch (Exception $e) {
                            $this->log(json_encode(array("params" => $goods, "message" => $e->getMessage())));
                        }
                        $goodsIDs[] = $checkResults['goods_id'];
                        if (!$checkResults['goods_id']) {
                            $this->log(json_encode(array("params" => $goods, "message" => "goods id get fiald")));
                        }
                        #商品相册
                        $addRe = $this->syncGoodsGallry($checkResults['goods_id'], $goodsGallery);
                    } else {
                        #2.添加商品
                        try {
                            $insertResult = $this->DB->insert('ecs_goods', $goods);
                            $goodsID = $this->DB->insert_id();
                            $this->syncGoodsAttr($val, $goodsID);
                        } catch (Exception $e) {
                            $this->log(json_encode(array("params" => $goods, "message" => $e->getMessage())));
                        }
                        $goodsIDs[] = $goodsID;
                        if (!$goodsID) {
                            $this->log(json_encode(array("params" => $goods, "message" => "goods id get fiald")));
                        }
                        #商品相册
                        $addRe = $this->syncGoodsGallry($goodsID, $goodsGallery);
                    }
                }
            }
            if (!$goodsIDs) {
                printJson(0, array(), '暂无需要同步的商品！');
            }
            foreach ($goodsIDs as $IDK => $IDV) {
                $allNumberSql = "SELECT SUM(product_number) product_number FROM `ecs_products` WHERE goods_id={$IDV}";
                $allNmber = $this->DB->get_one($allNumberSql);
                $updateGoodsNumberResult = $this->DB->update('ecs_goods', array('goods_number' => $allNmber['product_number']), "goods_id={$IDV}");
            }
            printJson(1, array('goods_id' => $goodsIDs), '');
        }
        printJson(0, array(), '暂无需要同步的商品！');
    }

    #同步商品品牌
    private function syncBrand($brandName = '')
    {
        if (trim($brandName) == '') {
            return 0;
        }
        $brandName = str_replace("'", "‘", trim($brandName));
        $checkBrandSql = "SELECT brand_id FROM `ecs_brand` WHERE brand_name='{$brandName}'";
        $checkBrand = $this->DB->get_one($checkBrandSql);
        if ($checkBrand && (int)$checkBrand['brand_id'] > 0) {
            return $checkBrand['brand_id'];
        }
        $insertData = array(
            'brand_name' => $brandName,
            'brand_logo' => '',
            'brand_desc' => '',
            'site_url' => 'http://',
            'sort_order' => 50,
            'is_show' => 1,
        );
        try {
            $insertResult = $this->DB->insert('ecs_brand', $insertData);
        } catch (Exception $e) {
            $this->log(json_encode(array('info' => $e->getMessage())), 'erp-brand');
            return 0;
        }
        $insertId = $this->DB->insert_id();
        return (int)$insertId;

    }

    #同步商品属性
    private function addAttribute($attrName = '', $attrCode = '', $goodsID = '', $price = '', $number = 0)
    {
        if (!$attrName || !$attrCode || !$goodsID) {
            return false;
        }
        $checkSql = "SELECT goods_attr_id FROM `ecs_goods_attr` WHERE goods_id={$goodsID} AND (attr_value='{$attrName}' OR attr_value='{$attrCode}')";
        $checkResult = $this->DB->get_one($checkSql);
        if (!$checkResult || (int)$checkResult['goods_attr_id'] < 1) {
            $goodsInfoSql = "SELECT goods_id, shop_price FROM `ecs_goods` WHERE goods_id={$goodsID}";
            $goodsInfo = $this->DB->get_one($goodsInfoSql);
            $price = $price - $goodsInfo['shop_price'];
            $insertData[] = array(
                'goods_id' => $goodsID,
                'attr_id' => 3,
                'attr_value' => $attrName,
                'attr_price' => $price,
            );
            $insertData[] = array(
                'goods_id' => $goodsID,
                'attr_id' => 4,
                'attr_value' => $attrCode,
                'attr_price' => '',
            );
            $insertData[] = array(
                'goods_id' => $goodsID,
                'attr_id' => 2,
                'attr_value' => $number,
                'attr_price' => '',
            );
            foreach ($insertData as $key => $val) {
                $insertRe[] = $this->DB->insert('ecs_goods_attr', $val);
                $sql = "UPDATE `ecs_goods` SET goods_type=1 WHERE goods_id={$goodsID}";
                $this->DB->query($sql);
            }
        }
        return true;
    }

    #给没有属性的商品加上通用属性
    public function addAttrForSingleGoods()
    {
//		$url = "http://180.168.83.222:8087/GetData.aspx?action=getmasterdata&key=lee";
        $url = $this->erp_path . "&action=getmasterdata";
        $goodsInfo = @file_get_contents($url);
        $goodsInfo = json_decode($goodsInfo, TRUE);
        if ($goodsInfo) {
            foreach ($goodsInfo as $key => $val) {
                $attrID = array();
                #判断skumc是否为空，是的话，则继续。
                if (trim($val['skumc']) == '' && trim($val['propertiesname']) == '') {
                    #拿当前erp数据的商品名称去goods表里匹配商品信息
                    $val['spmc'] = addslashes($val['spmc']);
                    $goodsSql = "SELECT goods_id,goods_number,shop_price FROM `ecs_goods` WHERE goods_name like '%{$val['spmc']}%'";
                    $goodsInfos = $this->DB->get_one($goodsSql);
                    if ($goodsInfos) {
                        #判断当前商品有没有属性
                        $attrSql = "SELECT goods_attr_id FROM `ecs_goods_attr` WHERE goods_id = {$goodsInfos['goods_id']}";
                        $attrInfo = $this->DB->get_one($attrSql);
                        if (!$attrInfo && (int)$attrInfo['goods_attr_id'] < 1) {
                            $attrName = '通用属性';
                            $sql = "SELECT attr_id,cat_id FROM `ecs_attribute` WHERE attr_name='{$attrName}'";
                            $checkRe = $this->DB->get_one($sql);
                            if ($checkRe) {
                                #创建属性
                                $goodsAttrArr = array(
                                    'goods_id' => $goodsInfos['goods_id'],
                                    'attr_id' => $checkRe['attr_id'],
                                    'attr_value' => '通用属性',
                                    'attr_price' => $goodsInfos['shop_price']
                                );
                                try {
                                    $goodsAttrAddRe = $this->DB->insert('ecs_goods_attr', $goodsAttrArr);
                                } catch (Exception $e) {
                                    $this->log(json_encode(array("params" => $goodsAttrArr, "message" => $e->getMessage())));
                                }
                                $attrID[] = $this->DB->insert_id();
                                #添加products表信息，商品条码（sptm）
                                $attrIDs = @implode("|", $attrID);
                                if ($attrIDs == '') {
                                    return false;
                                }
                                $insertData = array(
                                    'goods_id' => $goodsInfos['goods_id'],
                                    'goods_attr' => $attrIDs,
                                    'product_sn' => $val['sptm'],
                                    'product_number' => (int)$val['kc'],
                                    'product_price' => $val['price'],
                                );
                                $checkProSql = "SELECT product_id FROM `ecs_products` WHERE goods_id={$goodsInfos['goods_id']} AND goods_attr='{$attrIDs}'";
                                $checkProRe = $this->DB->get_one($checkProSql);
                                if ($checkProRe) {
                                    $this->DB->update('ecs_products', array('product_number' => (int)$goodsInfo['kc']), "goods_id={$goodsInfos['goods_id']} AND goods_attr='{$attrIDs}'");
                                    return true;
                                }
                                try {
                                    $insertRe = $this->DB->insert('ecs_products', $insertData);
                                } catch (Exception $e) {
                                    $this->log(json_encode(array("params" => $insertData, "message" => $e->getMessage())));
                                }
                                #更新库存和类型
                                if ($insertRe) {
                                    $this->DB->update('ecs_goods', array('goods_number' => (int)$val['kc'], 'goods_type' => 2), "goods_id={$goodsInfos['goods_id']}");
                                }
                            }
                        }
                    }
                } else {
                    continue;
                }
            }
        }
        echo 'success';
    }

    /*public function deleteGoodsType() {
        $sql = "select goods_id FROM `ecs_goods_attr` where attr_value = '通用属性'";
        $goodsInfo = $this->DB->get_all($sql);
        if($goodsInfo) {
            foreach ($goodsInfo as $key => $val) {
                $this->DB->update('ecs_goods', array('goods_type' => 0), "goods_id = {$val['goods_id']}");
                $this->DB->query("DELETE FROM ecs_products WHERE goods_id={$val['goods_id']}");
                $this->DB->query("DELETE FROM ecs_goods_attr WHERE goods_id={$val['goods_id']}");
            }
        }
    }*/

    public function deleteOtherData()
    {
        $sqlPro = "SELECT goods_id FROM `ecs_products` where 1 GROUP BY goods_id";
        $proInfo = $this->DB->get_all($sqlPro);
        $goodsIDs = array();
        foreach ($proInfo as $key => $val) {
            $goodsIDs[] = $val['goods_id'];
        }
        $goodsIDs[] = 6558;
        $goodsIDs[] = 6557;
        $goodsIDs[] = 6556;
        $goodsIDStr = implode("','", $goodsIDs);
        //echo $goodsIDStr;die;
        #$sqlSelect = "SELECT goods_id FROM ecs_goods where goods_id NOT IN ('{$goodsIDStr}')";
        #$goods = $this->DB->get_all($sqlSelect);
        #echo count($goods);
        #var_dump($goods);die;
        #执行删除操作
        $sqlDel = "DELETE FROM `ecs_goods` WHERE goods_id NOT IN ('{$goodsIDStr}')";
        $this->DB->query($sqlDel);
        echo "success.";
    }

    //同步商品属性（所有商品）
    public function syncGoodsAttrOnly()
    {
//		$url = "http://180.168.83.222:8087/GetData.aspx?action=getmasterdata&key=lee";
        $url = $this->erp_path . "&action=getmasterdata";
        $goodsInfo = @file_get_contents($url);
        $goodsInfo = json_decode($goodsInfo, TRUE);
        $updateResult = array();
        if ($goodsInfo) {
            foreach ($goodsInfo as $key => $val) {
                #TODO insert into or update goods table
                $checkGoodsSql = "SELECT goods_id,goods_number,shop_price FROM `ecs_goods` WHERE goods_sn = '{$val['spdm']}'";
                $checkResult = $this->DB->get_one($checkGoodsSql);
                if ($checkResult && $checkResult['goods_id'] > 0) {
                    $updateResult[$checkResult['goods_id']] = $this->syncGoodsAttr($val, $checkResult['goods_id']);
                }
            }
        }
        var_dump($updateResult);
    }

    private function syncGoodsAttr($goodsInfo, $goodsID)
    {
        #创建商品类型，根据name1，如果没有name1，则创建默认类型。（检测类型是否重复 重复的话只更新）
        $goodsInfo['name1'] = $goodsInfo['name1'] ? $goodsInfo['name1'] : '默认商品';
        $checkType = "SELECT cat_id FROM `ecs_goods_type` WHERE cat_name='{$goodsInfo['name1']}'";
        $checkResult = $this->DB->get_one($checkType);
        if (!$checkResult) {
            $typeArr = array(
                'cat_name' => $goodsInfo['name1'],
                'enabled' => 1,
                'attr_group' => $goodsInfo['name1'],
            );
            try {
                $addResult = $this->DB->insert('ecs_goods_type', $typeArr);
            } catch (Exception $e) {
                $this->log(json_encode(array("params" => $typeArr, "message" => $e->getMessage())));
            }
            $checkResult['cat_id'] = $this->DB->insert_id();
        }
        #$propInfo = explode(';', $goodsInfo['propertiesname']);
        $propInfo = array($goodsInfo['skumc']);
        #创建attribute，根据propertiesname
        if ($goodsInfo['propertiesname'] == '') {
            $propInfo = array('通用属性');
        }
        $attrID = array();

        foreach ($propInfo as $key => $val) {
            #$prop = explode(":", $val);
            #$attribute[$prop[2]][$prop[0]] = $prop[2];
            #$attribute[$prop[2]][$prop[1]] = $prop[3];
            #检查是否存在此属性
            #所有属性名称全叫通用属性
            $attrName = '通用属性';
            $sql = "SELECT attr_id,cat_id FROM `ecs_attribute` WHERE attr_name='{$attrName}'";
            $checkRe = $this->DB->get_one($sql);
            if (!$checkRe) {
                $attrArr = array(
                    'cat_id' => $checkResult['cat_id'],
                    'attr_name' => $attrName,
                    'attr_input_type' => 0,
                    'attr_type' => 1,
                    'attr_values' => '',
                    'attr_index' => 0,
                    'sort_order' => 0,
                    'is_linked' => 0,
                    'attr_group' => 0,
                );
                try {
                    $addRe = $this->DB->insert('ecs_attribute', $attrArr);
                } catch (Exception $e) {
                    $this->log(json_encode(array("params" => $attrArr, "message" => $e->getMessage())));
                }
                $checkRe['attr_id'] = $this->DB->insert_id();
            } else {
                $checkResult['cat_id'] = $checkRe['cat_id'];
            }
            #创建属性 ，根据propertiesname解析出的值
            #检查属性是否存在
            $attrSql = "SELECT goods_attr_id FROM `ecs_goods_attr` WHERE attr_value='{$val}' AND goods_id={$goodsID}";
            $checkAttrRe = $this->DB->get_one($attrSql);
            if (!$checkAttrRe) {
                #创建属性
                $goodsAttrArr = array(
                    'goods_id' => $goodsID,
                    'attr_id' => $checkRe['attr_id'],
                    'attr_value' => $val,
                    'attr_price' => $goodsInfo['price']
                );
                try {
                    $goodsAttrAddRe = $this->DB->insert('ecs_goods_attr', $goodsAttrArr);
                } catch (Exception $e) {
                    $this->log(json_encode(array("params" => $goodsAttrArr, "message" => $e->getMessage())));
                }
                $attrID[] = $this->DB->insert_id();

            } else {
                $attrID[] = $checkAttrRe['goods_attr_id'];
            }
            $sql = "UPDATE `ecs_goods` SET goods_type=" . $checkResult['cat_id'] . " WHERE goods_id={$goodsID}";
            $this->DB->query($sql);
        }


        #添加products表信息，商品条码（sptm）
        $attrIDs = implode("|", $attrID);
        if ($attrIDs == '') {
            return false;
        }
        $insertData = array(
            'goods_id' => $goodsID,
            'goods_attr' => $attrIDs,
            'product_sn' => $goodsInfo['sptm'],
            'product_number' => (int)$goodsInfo['kc'],
            'product_price' => $goodsInfo['price'],
        );
        $checkProSql = "SELECT product_id FROM `ecs_products` WHERE goods_id={$goodsID} AND goods_attr='{$attrIDs}'";
        $checkProRe = $this->DB->get_one($checkProSql);
        if ($checkProRe) {
            $this->DB->update('ecs_products', array('product_number' => (int)$goodsInfo['kc']), "goods_id={$goodsID} AND goods_attr='{$attrIDs}'");
            return true;
        }
        try {
            $insertRe = $this->DB->insert('ecs_products', $insertData);
        } catch (Exception $e) {
            $this->log(json_encode(array("params" => $insertData, "message" => $e->getMessage())));
        }

        //更新商品价格，首先查询当前商品的shop_price 然后和返回来的价格比对 如果当前价格比shop_price低的话，则把当前价格给更新上去
        /*$goodsPriceSql = "SELECT shop_price FROM `ecs_goods` WHERE goods_id={$goodsID}";
        $goodsPrice = $this->DB->get_one($goodsPriceSql);
        if($goodsPrice && $goodsPrice['shop_price'] > $goodsInfo['price']) {
            if((int)$goodsInfo['price'] > 0) {
                $updateGoodsPrice = "UPDATE `ecs_goods` set shop_price = {$goodsInfo['price']} WHERE goods_id={$goodsID}";
                $this->DB->query($updateGoodsPrice);
            }
        }*/
        return true;
    }

    //更新库存
    private function getGoodsNumber($goodsID)
    {
        $goodsNumberSql = "SELECT goods_number FROM `ecs_goods` WHERE goods_id={$goodsID}";
        $goodsNumber = $this->DB->get_one($goodsNumberSql);
        if ((int)$goodsNumber['goods_number'] > 0) {
            return $goodsNumber['goods_number'];
        }
        return 0;
    }

    private function log($content, $name = "info")
    {
        $file = dirname(__FILE__) . "/logs/";
        if (!is_dir($file)) {
            mkdir($file);
        }
        $fileObj = fopen($file . 'logs-' . $name . '.txt', 'a+');
        fwrite($fileObj, $content . "\r\t");
        fclose($fileObj);
    }

    #name : 保存图片到本地
    private function saveImageToLocal($gallery = array())
    {
        if (empty($gallery)) {
            return $gallery;
        }
    }

    #name ： 同步商品相册
    private function syncGoodsGallry($goodsID, $gallery, $insert = true)
    {
        if (!$gallery || !$goodsID) {
            return false;
        }
        foreach ($gallery as $key => $val) {
            $checkSql = "SELECT img_id FROM `ecs_goods_gallery` WHERE img_url='{$val}' AND goods_id={$goodsID}";
            $checkResult = $this->DB->get_one($checkSql);
            if ($checkResult) {
                continue;
            }
            $galleryData = array(
                'goods_id' => $goodsID,
                'img_url' => $val,
                'img_desc' => '',
                'thumb_url' => $val,
                'img_original' => $val,
            );
            if (count($galleryData) > 0) {
                try {
                    $insertResult = $this->DB->insert('ecs_goods_gallery', $galleryData, true);
                } catch (Exception $e) {
                    $this->log(json_encode(array("paramsphoto" => $galleryData, "message" => "insert goods gallery fiald")));
                }
                unset($galleryData);
            }
        }
        return true;
    }

    #name ： 获取加密字符串
    private function createDecryption()
    {
        $time = date("Y-m-d H:i");
        $sign = MD5(MD5($time) . MD5('ecshoptoERP'));
        return $sign;
    }

    #name ：验证加密字符串
    private function decryption($requestSign = '')
    {
        $time = date("Y-m-d H:i");

        $sign = MD5(MD5($time) . MD5('ecshoptoERP'));
        if (trim($requestSign) != $sign) {
            return false;
        }
        return true;
    }

    #name ：获取商品条码
    private function getGoodsTmByProductId($productId)
    {
        if (!$productId) {
            return false;
        }
        $proSql = "SELECT product_sn FROM `" . $this->db_name . "products` WHERE product_id={$productId}";
        $proInfo = $this->DB->get_one($proSql);
        if ($proInfo) {
            return $proInfo['product_sn'];
        }
        return false;
    }

    #name : 获取地区信息
    #param ：provinceID 省份id
    #param : cityID 城市id
    #param ：areaID 地区id
    private function getAreaInfo($provinceID, $cityID, $areaID)
    {
        $sqlP = "SELECT region_id,region_name FROM `" . $this->db_name . "region` WHERE region_id = {$provinceID}";
        $sqlC = "SELECT region_id,region_name FROM `" . $this->db_name . "region` WHERE region_id = {$cityID}";
        $sqlA = "SELECT region_id,region_name FROM `" . $this->db_name . "region` WHERE region_id = {$areaID}";
        $respons = array();
        $DB = dbLink();
        $respons['province'] = $DB->get_one($sqlP);
        $respons['city'] = $DB->get_one($sqlC);
        $respons['area'] = $DB->get_one($sqlA);
        return $respons;
    }


    /**
     * 获取数据写入本地
     *
     */
    public function getGoodsNumberPutLocal()
    {
//        $url = "http://180.168.83.222:8087/GetData.aspx?action=getonhands&key=lee";
        $url = $this->erp_path . "&action=getonhands";
        $goodsInfo = @file_get_contents($url);
//        $goodsInfo = json_decode($goodsInfo, TRUE);
//         echo "<pre>";       
//         var_dump($goodsInfo);exit;
        file_put_contents("./goodsnumber.txt", $goodsInfo);
        header("location:http://ts.leepet.com/plugins/API.v1.0?&a=erp&m=updateGoodsNumber");

    }

    public function getNullSn()
    {
        $DB = dbLink();
        $goodsInfo = $DB->get_all("select goods_id,goods_sn from `ecs_goods` where add_time<>'1408557600'");
        echo "<pre>";
        var_dump($goodsInfo);
        exit;

    }

    /**
     *  更新商品库存
     *  2014-7-15
     */
    public function updateGoodsNumber()
    {
        $goodsInfo = file_get_contents("./goodsnumber.txt");
        $goodsInfo = json_decode($goodsInfo, TRUE);
        $time = time();
        $DB = dbLink();
        $i = 0;
        if ($_GET['i']) {
            $i = $_GET['i'];
        }

        for ($i; $i <= count($goodsInfo); $i++) {
            $sn = $goodsInfo[$i]['sptm'];
            $num = $goodsInfo[$i]['minkys'];
            $isHere = $DB->get_one("select g.goods_id from `ecs_goods` as g LEFT JOIN `ecs_products` as p ON p.goods_id=g.goods_id WHERE g.goods_sn='" . $sn . "' or p.product_sn='" . $sn . "'");
            if ($isHere) {
                $resP = $DB->get_one("select goods_id from `ecs_products` where product_sn='" . $sn . "'");
                if ($resP) {
                    $r = $DB->query("update `ecs_products` set product_number='" . $num . "' where product_sn='" . $sn . "'");
                    if ($r) {
                        $DB->query("update `ecs_goods` SET add_time='" . $time . "' where goods_id=" . $resP['goods_id']);
                        $this->log($isHere['goods_id'], 'update_success_number');
                    } else {
                        $this->log("goods_id=" . $isHere['goods_id'], 'update_error_number');
                    }
                } else {
                    $s = $DB->query("update `ecs_goods` set add_time='" . $time . "',goods_number='" . $num . "' where goods_sn='" . $sn . "'");
                    if ($s) {
                        $this->log($isHere['goods_id'], 'update_success_number');
                    } else {
                        $this->log("goods_id=" . $isHere['goods_id'], 'update_error_number');
                    }
                }

            } else {
                $isErrorSn = $DB->get_one("select goods_id from `ecs_goods` where goods_sn='" . $goodsInfo[$i]['spdm'] . "'");
                if ($isErrorSn) {
                    $DB->query("UPDATE `ecs_goods` SET add_time='" . $time . "',goods_sn='" . $goodsInfo[$i]['sptm'] . "'  WHERE goods_id=" . $isErrorSn['goods_id']);
                    $this->log($isErrorSn['goods_id'], 'update_success_number');
                } else {
                    $this->log($sn, 'update_null_number');
                }
            }
            if ($i % 30 == 0 && $i != 0) {
                $i++;
                echo "<script>window.location.href='http://ts.leepet.com/plugins/API.v1.0/?&a=erp&m=updateGoodsNumber&i=" . $i . "'</script>";
                exit;
            }
        }
    }

    /**
     * 接口访问post函数
     * @return mixed    返回接受到的数据
     */
    private function urlpost($url, $orderInfo)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $orderInfo);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, ture);
    }


    #更新库存同步，根据商品条码（sptm） 有效 手动执行 shell.php by jw   2017-1-17
    public function updateKCBySptm()
    {
        $this->log("crontab auto run logs.", "crontab-shell");
        $url = $this->erp_path . "&action=getonhands";
        //$url = "http://180.168.83.222:8087/GetData.aspx?action=getonhands&key=lee";
        //$goodsInfo = @file_get_contents($url);
        $goodsInfo = $this->urlpost($url, null);

        $updateResult = array();
        if ($goodsInfo) {
            foreach ($goodsInfo as $gkey => $gval) {
                #查询当前记录对应的商品
                $gval['sptm'] = trim($gval['sptm'], "'");
                $checkSqls = "SELECT product_sn,product_number,goods_id FROM " . $this->db_name . "products WHERE product_sn = '{$gval['sptm']}'";
                $goodsInfomations = $this->DB->get_one($checkSqls);
                #清除当前记录对应的商品库存
                if ($goodsInfomations && (int)$goodsInfomations['goods_id'] > 0) {
                    $delNumberSql = "UPDATE `" . $this->db_name . "goods` SET goods_number = 0 WHERE goods_id = {$goodsInfomations['goods_id']}";
                    $this->DB->query($delNumberSql);
                }
                #$delNumberSql = "UPDATE `ecs_goods` SET goods_number = 0 ";
            }

            foreach ($goodsInfo as $key => $val) {
                if (trim($val['sptm']) != '') {
                    $val['sptm'] = trim($val['sptm'], "'");
                    $val['spdm'] = trim($val['spdm'], "'");
                    $checkSql = "SELECT product_sn,product_number,goods_id FROM  " . $this->db_name . "products WHERE product_sn = '{$val['sptm']}'";
                    try {
                        $checkResult = $this->DB->get_one($checkSql);
                    } catch (Exception $e) {
                        continue;
                    }
                    if ($checkResult && $checkResult['product_sn'] != '') {
                        $productNum = (int)$val['minkys'];
                        $updateResult['ecs_products'][] = $this->DB->update($this->db_name . 'products', array('product_number' => $productNum), "product_sn = '{$val['sptm']}'");
                        $goodsSql = "SELECT goods_id, goods_number FROM  " . $this->db_name . "goods WHERE goods_id = {$checkResult['goods_id']}";
                        $goods = $this->DB->get_one($goodsSql);
                        $goods['goods_id'] = (int)$goods['goods_id'];
                        $updateResult['ecs_goods'][] = $this->DB->update($this->db_name . 'goods', array("goods_number" => (int)$goods['goods_number'] + $productNum), "goods_id = {$goods['goods_id']}");
                    } else {
                        #直接更新goods表库存
                        $updateResult['ecs_goods_single'][] = $this->DB->update($this->db_name . 'goods', array('goods_number' => $val['minkys']), "goods_sn = '{$val['spdm']}'");
                    }
                }
            }
        }
        //var_dump($updateResult);
    }

    public function test()
    {
        file_put_contents("../shell.txt", json_encode($_REQUEST));
        $fileObj = fopen("../shell.txt", 'a+');
        fwrite($fileObj, json_encode($_REQUEST) . "\r\t");
        fclose($fileObj);
    }

    #通过商品条码，更新商品描述
    public function updateGoodsDescBySptm()
    {
//    	$url = "http://180.168.83.222:8087/GetData.aspx?action=getmasterdata&key=lee";
        $url = $this->erp_path . "&action=getmasterdata";
        $goodsInfo = @file_get_contents($url);
        $goodsInfo = json_decode($goodsInfo, TRUE);
        $updateResult = array();
        if ($goodsInfo) {
            foreach ($goodsInfo as $gkey => $gval) {
                #查询当前记录对应的商品
                $checkSqls = "SELECT product_sn,product_number,goods_id FROM ecs_products WHERE product_sn = '{$gval['sptm']}'";
                $goodsInfomations = $this->DB->get_one($checkSqls);
                if (!$goodsInfomations || (int)$goodsInfomations['goods_id'] < 1) {
                    continue;
                }
                #根据查询出的goods_id去goods表查询当前记录是否有商品描述，如果没有，则更新
                $sqlGoods = "SELECT goods_desc,goods_id FROM `ecs_goods` WHERE goods_id={$goodsInfomations['goods_id']}";
                $goodsInfos = $this->DB->get_one($sqlGoods);
                if ($goodsInfos && trim($goodsInfos['goods_desc']) == '') {
                    #更新商品描述//
                    $updateResult[] = $this->DB->update('ecs_goods', array('goods_desc' => trim($gval['goods_desc'])), "goods_id='{$goodsInfos['goods_id']}'");
                }
            }
        }
        var_dump($updateResult);
    }


    #把Q仔商品信息导入到文件里
    public function pushQZDataIntoFile()
    {
//    	$url = "http://180.168.83.222:8087/GetData.aspx?action=getmasterdata_qzai&key=lee";
        $url = $this->erp_path . "&action=getmasterdata_qzai";
        $goodsInfo = @file_get_contents($url);
        #$goodsInfo = json_decode($goodsInfo, TRUE);
        var_dump($goodsInfo);
        die;
        file_put_contents(dirname(__FILE__) . "/qzaiData.txt", $goodsInfo);
        echo "SUCEESS!";
    }

    public function bakErpGoods()
    {
//    	$url = "http://180.168.83.222:8087/GetData.aspx?action=getmasterdata_cy&key=lee";
        $url = $this->erp_path . "&action=getmasterdata_cy";
        $goodsInfo = @file_get_contents($url);
        $goodsInfo = json_decode($goodsInfo, TRUE);
        var_dump($goodsInfo);
        die;
    }

    public function test123()
    {
        echo date("Y-m-d H:i:s");
        die;
    }

    //在ERP取消订单
    public function cancelOrder()
    {
        $orderID = $_REQUEST['order_id'] ? $_REQUEST['order_id'] : printJson(0, '', '参数错误！缺少order_id');
        $orderSn = $_REQUEST['order_sn'] ? $_REQUEST['order_sn'] : printJson(0, '', '参数错误！缺少order_sn');
        if ($orderSn) {
            $url = $this->erp_path . "&action=zuofei&orderno=" . $orderSn;
            $rs = @file_get_contents($url);
            $this->cancelOrderResult(json_decode($rs, true), $orderSn, $orderID);
        }
    }

    //取消订单返回结果处理
    protected function cancelOrderResult($msg, $order_sn, $order_id)
    {
        echo json_encode($msg[0]);
        $this->writeLog($msg[0], $order_sn, $order_id);
//		if($msg['zuofei_status'] == 0){
//			$this->sendMsg($msg[0],$order_sn);
//		}
    }

    //发送通知
    private function sendMsg($msg, $order_sn)
    {
        //发送消息
    }

    //写入日志库
    private function writeLog($msg, $order_sn, $order_id)
    {
        $insertData = array(
            'order_id' => $order_id,
            'order_sn' => $order_sn,
            'status' => $msg['zuofei_status'],
            'message' => $msg['zuofei_message'],
            'canceltime' => time(),
            'type' => $msg['zuofei_type']
        );
        $this->DB->insert('ecs_erp_cancel_log', $insertData);

    }

    public function getOrderStatus()
    {
        $orderSn = $_REQUEST['order_sn'] ? $_REQUEST['order_sn'] : printJson(0, '', '参数错误！缺少order_sn');
        if ($orderSn) {
            $url = $this->erp_path . "&action=getordertatus1&orderno=" . $orderSn;
            $rs = @file_get_contents($url);
            echo $rs;
        }
    }
}

?>
