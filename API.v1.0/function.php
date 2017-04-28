<?php
header("Content-type: text/html; charset=utf-8");
function printJson($status = 0, $data = array(), $message = '') {
	$jsonData = array(
		'status' => $status,
	);
	if(is_array($data)) {
		$jsonData['data'] = $data;
	}
	$jsonData['msg'] = $message;
	echo json_encode($jsonData);die;
}

/**
 * 检查参数合法
 * @param string $sql_str
 * @return int
 */
function inject_check($sql_str='') {
	return preg_match('select|insert|and|or|update|delete|\'|\/\*|\*|\.\.\/|\.\/|union|into|load_file|outfile', $sql_str);
}
function checkParam($rule = array(), $params = array()) {
    $paramater = array();
    $version = trim($params['version']) != '' ? trim($params['version']) : '';
    foreach ($rule as $k => $v) {
        if ($params[$v]) {
        	 if($version == '1.0') {
        	 	$paramater[$v] = base64_decode(base64_decode($params[$v]));
        	 	//$paramater[$v] = $params[$v];
        	 } else {
        	 	$paramater[$v] = $params[$v];
        	 }
        }
    }
    if (count($paramater) < count($rule)) {
        return false;
    }
    return $paramater;
}

function base64($rule = array(), $params = array()) {
	$paramater = array();
	foreach ($rule as $k => $v) {
		if ($params[$v]) {
			$paramater[$v] = base64_decode(base64_decode($params[$v]));
		}
	}
	if (count($paramater) < count($rule)) {
		return false;
	}
	return $paramater;
}

function isFile($filePath) {
	if(is_file($filePath)) {
		return true;
	}
	return false;
}

function isMethod($action, $method) {
	if(method_exists($action, $method)) {
		return true;
	}
	return false;
}

function dbLink() {
	include_once DB_PATH . "MyDB.php";
	return new MYDB();
}

function getULogo($number, $del = true) {
	return "http://uc.chinapet.com/avatar.php?uid=".$number."&size=middle";
	static $respons = '';
	static $num = '';
	$delimit = '/';
	$strlen = strlen($number);
	if($del) {
		$respons = '';
		if($strlen < 9) {
			for($i = 1, $len = 9 - $strlen; $i <= $len; $i++) {
				$number = '-' . $number;
			}
		}
	}
	$strlen = strlen($number);
	if(strlen($number) >= 2) {
		$respons = $delimit . substr($number, -2) . $respons;
		$num = substr($number, 0, $strlen-2);
	}
	if(strlen($num) >= 2) {
		getULogo($num, false);
	}
	return 'http://uc.chinapet.com/data/avatar/0' . str_replace('-', '0', trim($respons, '/')) . '_avatar_middle.jpg';
}


?>