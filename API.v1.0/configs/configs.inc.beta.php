<?php
/**
 * Created by PhpStorm.
 * User: liupeng
 * Date: 16/6/5
 * Time: 下午12:00
 */

$erp_config = array(
    'protocol' => 'http://',
    'domain' => '180.168.167.154',
    'port' => '8099',
    'path' => 'GetData.aspx',
    'key' =>'key=leepet121',
    'db_name'=> 'wxlc_',
);

$erp_path = $erp_config['protocol'] . $erp_config['domain'].":".$erp_config['port']
    .'/'.$erp_config['path'].'?'.$erp_config['key'];
?>