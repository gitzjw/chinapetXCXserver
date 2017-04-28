<?PHP
Class  VERSION{
    public function get_version(){
        require_once('/alidata/www/www.leepet.com/plugins/API.v1.0/waf.php');
        $versioninfo ='{"apk":{"version":"3","content":"1.全新的消息机制，为您时刻掌握帖子的最新动态\n2.优化账号创建体验，更加方便快捷\n3.全新界面，带给您更好的视觉感受\n4.优化帖子详情页，展现更丰富的内容\n5.修复已知bug","url":"http://chinapetupload.oss-cn-hangzhou.aliyuncs.com/leepet1.2.apk","force":"0"},"ios":{"version":"1.2","content":"1.全新的消息机制，为您时刻掌握帖子的最新动态\n2.优化账号创建体验，更加方便快捷\n3.全新界面，带给您更好的视觉感受\n4.优化帖子详情页，展现更丰富的内容\n5.修复已知bug","url":"https://itunes.apple.com/cn/app/le-chong-le-chong-ju-le-bu/id898205148?mt=8","force":"0"}}';
        if(!$_REQUEST['type']){
            echo $versioninfo;exit;
        }

        $params = checkParam(array('version','type'), $_REQUEST);
        if(!$params) {
            printJson(0, '', '参数错误！');
        }

        $user_version =$_REQUEST['version'];
        $type =$oldtype=$_REQUEST['type'];
        $appid= $_REQUEST['appid'];

        if(empty($appid)){
            echo $versioninfo;exit;
        }

        if($type=='apk'){
            $type="android-phone";
        }elseif($type=='ios'){
            $type=$type."-phone";
        }else{
            printJson(0, '', '应用信息不存在');
        }
        $DB = dbLink();
        $sql="select * from ecs_mobile_client_version where type='$type' and status=1 order by version desc limit 1";
        $info[$oldtype]= $DB->get_one($sql);
        $oldinfo = $DB->get_one("SELECT * FROM `ecs_mobile_client_version` where type='$type' and version='$user_version'");
        if($info[$oldtype]){
            if($user_version >= $info[$oldtype]['version']){
                $infos[$oldtype]['needup'] = '0';
            }else{
                $infos[$oldtype]['needup'] = '1';
                if(!empty($oldinfo) && $oldinfo['is_use'] == '0'){
                    $infos[$oldtype]['force']='1';
                }else{
                    $infos[$oldtype]['force']='0';
                }
                $infos[$oldtype]['version'] = $info[$oldtype]['version'];
                $infos[$oldtype]['content'] = $info[$oldtype]['content'];
                $infos[$oldtype]['url']     = $info[$oldtype]['url'];
            }
            die(json_encode($infos));exit;
        }else{
            printJson(0, '', '版本信息不存在');
        }
    }
//      public function get_version(){
//         echo $this->version;exit;
//      }
}

?>




