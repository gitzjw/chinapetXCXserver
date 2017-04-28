<?php
header("Content-type: text/html; charset=utf-8");
error_reporting(E_ERROR);
include dirname(__FILE__) . "/define.php";
include dirname(__FILE__) . "/function.php";
if($_REQUEST['a'] && trim($_REQUEST['a']) != '' && isFile(dirname(__FILE__) .'/interface/'. trim($_REQUEST['a']). '.php')) {
	include dirname(__FILE__) .'/interface/'. trim($_REQUEST['a']). '.php';
	$actionName = strtoupper($_REQUEST['a']);
	$action = new $actionName();
	if($_REQUEST['m'] && trim($_REQUEST['m']) != '' && isMethod($action, trim($_REQUEST['m']))) {
		return $action->$_REQUEST['m']();
	} else {
		die("方法不存在！");
	}
} else {
	die("Action不存在！");
}
?>