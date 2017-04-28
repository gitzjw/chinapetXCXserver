<?php

/**
 * Created by PhpStorm.
 * User: lining
 * Date: 15/3/3
 * Time: 上午11:11
 */
include_once('update.php');


class bbs
{

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


    function getDataForXML($res_data,$node)
    {
        $xml = simplexml_load_string($res_data);
        $result = $xml->xpath($node);

        while(list( , $node) = each($result))
        {
            return $node;
        }
    }

    public function text(){
        echo "测试链接";
    }



    public function index($pn)
    {
        $pn = intval($_REQUEST['pn']);;
        if (empty($pn) and !is_int($pn)) {
            $pn = 0;
        }
        $url = 'http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=threadIntroduceForIndexV4&friendRecommend=0&service=threadIntroduceForIndexV4&sign=dc37acf1d6d079cdd5350989f758340d&version=d1bd83a33f1a841ab7fda32449746cc4&page=' . $pn . '&pagesize=10';
        $data = $this->urlpost($url);
        echo $data;
    }


    public function indexbanner()
    {
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=indexbanner";
        $data = $this->urlpost($url);
        echo $data;
    }

    /*
     * 帖子详情*/
    public function threadDetailNew($tid)
    {
        $key = trim(addslashes($_REQUEST['re_session']));
        $user = "";
        if (!empty($key)) {
            $uid = $this->checkBbsId();
            $user = "&uid=" . $uid . "&type=xcx";
        }

        $tid = intval($_REQUEST['tid']);
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=threadDetailNew&service=threadDetailNew&sign=73e38dcbf24327b06fba8b66b8bcf6c5&version=d1bd83a33f1a841ab7fda32449746cc4&tid=" . $tid . $user;
        $data = $this->urlpost($url);
        echo $data;
    }

    /*tid 帖子id
     *pn 页数
     * 帖子回复列表，虽然详情里也包含了postarray但是不全
     */
    public function threadShowLayerNew($tid, $pn)
    {
        $tid = intval($_REQUEST['tid']);
        $pn = intval($_REQUEST['pn']);
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=threadShowLayerNew&isdesc=1&onlyauthor=0&pagesize=10&service=threadShowLayerNew&sign=f7575635b1e34e8a9a06f6b81c4cb948&uid=&version=d1bd83a33f1a841ab7fda32449746cc4&page=" . $pn . "&tid=" . $tid;
        $data = $this->urlpost($url);
        echo $data;
    }


    public function actDetailByTageId($id)
    {
        $id = intval($_REQUEST['id']);
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=actDetailByTageId&commtagType=1&page=1&pageSize=10&service=actDetailByTageId&sign=4691bd9fc2b9eea84d8a72953d226602&tagType=activityTag&version=d1bd83a33f1a841ab7fda32449746cc4&tagid=" . $id;
        $data = $this->urlpost($url);
        echo $data;
    }

    /*所有圈子列表（不分类）*/

    public function fornumNewList()
    {

        $memcache = new Memcache;
        $memcache->connect('127.0.0.1', 11222) or die ("Could not connect");
        $name = "fornumNewList";
        $list = $memcache->get($name);
        if (empty($menus)) {
            $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=threadForumSelectV3&id=leepet_thread:api&action=threadForumSelectV3&page=1&service=threadForumSelectV3&sign=7967f3ae8bf587cdd162d87a9f51c7b9&version=d1bd83a33f1a841ab7fda32449746cc4";
            $data = $this->urlpost($url);
            $list = json_decode($data)->forumList;
            $list = json_encode($list);
            $status = $memcache->set($name, $list, false, 43200);//半天
        }
        echo $list;
    }


    /**
     * 圈子列表
     * fid    2汪 3喵 14545572595其他
     */
    public function indexforumNew($fid)
    {

        $memcache = new Memcache;
        $memcache->connect('127.0.0.1', 11222) or die ("Could not connect");
        $menus_name = "indexforumNew";
        $menus = $memcache->get($menus_name);
        if (empty($menus)) {

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
            $menus = json_encode($forum);
            $status = $memcache->set($menus_name, $menus, false, 43200);//半天
        }
        echo $menus;
    }

    /**
     * 具体圈子内容贴子列表
     *tagid --》id具体圈子
     */
    public function actDetailByTageIdV4($id)
    {
        $id = intval($_REQUEST['id']);
        $pn = empty(intval($_REQUEST['pn'])) ? 1 : intval($_REQUEST['pn']);
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=actDetailByTageIdV4&filter=&pagesize=10&service=actDetailByTageIdV4&sign=988b0be7278c2a7d0e6102920e24a565&tagType=forumTag&version=d1bd83a33f1a841ab7fda32449746cc4&tagid=" . $id . "&page=" . $pn;
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
    /*
     * 效验用户登录状态*/
    private function checkBbsId()
    {

        $key = trim(addslashes($_REQUEST['re_session']));
        $memcache = new Memcache;
        $memcache->connect('127.0.0.1', 11222) or die ("Could not connect");
        $rd_value = $memcache->get($key);
        if (empty($rd_value)) {
            $data = array(
                "status" => "404",
                "msg" => "登录超时，请进会员中心点击刷新登录",
            );
            echo json_encode($data);
            die;
        }
        $valueData = json_decode($rd_value);
        $openid = $valueData['0'];
        $session_key = $valueData['1'];
        $bbs_userid = $valueData['2'];
        if (empty($bbs_userid)) {
            $data = array(
                "status" => "404",
                "msg" => "用户不存在",
            );
            echo json_encode($data);
            die;
        } else {
            return $bbs_userid;
        }
    }

    /*
     * 文件上传
     * 普通上传保存本服务器，暂时不用
     * */
    public function update()
    {

        $uid = $this->checkBbsId();

        $file = $_FILES['file'];

        $up = new FileUpload();
        $up->set("path", "../../data/bbsImage/");
        $up->set("maxsize", 2000000);
        $up->set("allowtype", array("gif", "png", "jpg", "jpeg", 'mp4', 'avi', 'mp3'));
        $up->set("israndname", true, $uid);
        if ($up->upload($_FILES, $uid)) {
            //获取上传后文件名子
            $name = $up->getFileName();
            $url = "https://" . $_SERVER['HTTP_HOST'] . "/data/bbsImage/" . $name;
            $data = array(
                'status' => '200',
                'msg' => "上传成功",
                'url' => $url
            );

            echo json_encode($data);
            die;

        } else {

            //获取上传失败以后的错误提示
            $msg = ($up->getErrorMsg());
            $data = array(
                'status' => '301',
                'msg' => $msg,
                'url' => null
            );
            echo json_encode($data);
            die;
        }


    }

    /** php 发送流文件 bbs图片上传宠物中国服务器,
     * @param  String $uid
     * @param  String $file 要发送的文件
     * @return boolean
     */
    public function bbsUpdate()
    {
        $uid = $this->checkBbsId();

        //类型判断
        $type = explode("/", $_FILES["file"]['type']);
        if (!in_array($type['1'], array('jpg', 'gif', 'png', 'jpeg'))) {
            $data = array(
                'status' => '404',
                'error' => '-1',
                'msg' => '未允许类型',
            );
            echo json_encode($data);
            die;
        }
        $size = 7000000;
        if ($_FILES["file"]['size'] > $size) {
            $data = array(
                'status' => '404',
                'error' => '-2',
                'msg' => '文件不能超过' . $size . "字节",
            );
            echo json_encode($data);
            die;
        }
        $fp = fopen($_FILES["file"]["tmp_name"], "rb");
        $buf = fread($fp, $_FILES["file"]["size"]);
        if (empty($_FILES) || empty($buf)) {
            $data = array(
                'status' => '301',
                'msg' => '上传失败',
                'url' => null
            );
            echo json_encode($data);
        }

        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=newattachmentXcx&version=d1bd83a33f1a841ab7fda32449746cc4&service=newattachmentXcx&sign=5a90da4cb4cab68342afcc7618c75662&version=d1bd83a33f1a841ab7fda32449746cc4";

        $data = array(
            'uid' => $uid,
            'tmp_name' => $_FILES["file"]['tmp_name'],
            'name' => $_FILES["file"]['name'],
            'size' => $_FILES["file"]['size'],
            'type' => $_FILES["file"]['type'],
            'buf' => base64_encode($buf),
        );
        $status = $this->urlpost($url, $data);
        echo $status;
    }


    /** php 发送流视频 上传宠物中国服务器,
     * @param  String $uid
     * @param  String $file 要发送的文件
     * @return boolean
     */
    public function bbsUpdateVideo()
    {
        $uid = $this->checkBbsId();

        //类型判断
        $type = explode("/", $_FILES["file"]['type']);
        if (!in_array($type['1'], array('mp4', 'avi'))) {
            $data = array(
                'status' => '404',
                'error' => '-1',
                'msg' => '请上传mp4、avi格式',
            );
            echo json_encode($data);
            die;
        }
        $size = 9000000;
        if ($_FILES["file"]['size'] > $size) {
            $data = array(
                'status' => '404',
                'error' => '-2',
                'msg' => '文件不能超过' . $size . "字节",
            );
            echo json_encode($data);
            die;
        }
        $fp = fopen($_FILES["file"]["tmp_name"], "rb");
        $buf = fread($fp, $_FILES["file"]["size"]);
        if (empty($_FILES) || empty($buf)) {
            $data = array(
                'status' => '301',
                'msg' => '上传失败',
                'url' => null
            );
            echo json_encode($data);
        }

        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=updatevideoXcx&version=d1bd83a33f1a841ab7fda32449746cc4&service=updatevideoXcx&sign=5a90da4cb4cab68342afcc7618c75662&version=d1bd83a33f1a841ab7fda32449746cc4";

        $data = array(
            'uid' => $uid,
            'tmp_name' => $_FILES["file"]['tmp_name'],
            'name' => $_FILES["file"]['name'],
            'size' => $_FILES["file"]['size'],
            'type' => $_FILES["file"]['type'],
            'buf' => base64_encode($buf),
        );
        $status = $this->urlpost($url, $data);
        echo $status;
    }

    /**
     * DATE: 2017-3-23
     * 【发帖】
     *
     * @param    uid        用户ID  [必选]
     * @param    fid        圈子ID  [必选]
     * @param    title    发帖标题[必选] @2016-3-7 功能修改 标题【可选】
     * @param    content    发帖内容[必选]
     *
     * @param    postLocation {
     * mapx        帖子区域坐标x
     * mapy        帖子区域坐标y
     * location    帖子区域名称
     * }
     *
     * @param    tagid            标签ID    在活动界面发帖[必传]
     * @param    aidlist        图片id拼接字符串
     * @param    videoidlist    视频ID
     * @param    tagidlist    标签ID拼串
     *
     * @param    isshare        是否分享
     * @param    threadsign    用户帖子唯一标识
     *
     */
    public function threadPostNew()
    {
        $uid = $this->checkBbsId();
        $data = json_decode($_REQUEST['data']);
        $fid = intval($_REQUEST['fid']);
        $aidlist = (string)$_REQUEST['aidlist'];
        $videoidlist = $_REQUEST['videoidlist'];
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=threadPostV3&service=threadPostV3&sign=e35fb3e0108f049915222fb7536e3f2b&version=d1bd83a33f1a841ab7fda32449746cc4";
        $postInfo['uid'] = $uid;
        $postInfo['fid'] = $fid;
        $postInfo['aidlist'] = $aidlist;
        $postInfo['videoidlist'] = $videoidlist;
        $postInfo['content'] = $data->text;
        $postInfo['title'] = $data->title;
        $postInfo = json_encode($postInfo);
        $url = $url . "&postInfo=" . $postInfo;

        $a = $this->urlpost($url);
        if ($a->result == 0) {
            $data = array(
                'status' => '200',
                'msg' => "发布成功",
            );

            echo json_encode($data);
            die;
        } else {
            $data = array(
                'status' => '404',
                'msg' => "发布失败",
            );

            echo json_encode($data);
            die;
        }
    }

    /** 帖子回复,
     * @param  String $uid
     * @param         String  tid
     * @return boolean
     */

    public function postReplyNew()
    {
        $tid = intval($_REQUEST['tid']);
        $content = htmlentities((string)$_REQUEST['content']);
        $version = ($_REQUEST['version']);
        $platform = ($_REQUEST['platform']);

        $uid = $this->checkBbsId();

        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=postReplyNew&uid=" . $uid;
        $data = array(
            'content' => $content,
            'platform' => "iPhone8,1",
            'tid' => $tid,
            'uid' => $uid,
            'version' => $version,
            'type' => 'xcx'
        );
        $result = $this->urlpost($url, $data);
        if ($result['result'] == 0) {
            $data = array(
                'status' => '200',
                'msg' => "发布成功",
            );

            echo json_encode($data);
            die;
        } else {
            $data = array(
                'status' => '404',
                'msg' => "发布失败",
            );

            echo json_encode($data);
            die;
        }

    }

    /*
     * 点赞
    */
    public function praisePost()
    {
        $tid = intval($_REQUEST['tid']);
        $iscancel = intval($_REQUEST['iscancel']);
        $uid = $this->checkBbsId();
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=xcxpraisePost&version=d1bd83a33f1a841ab7fda32449746cc4&type=xcx&service=xcxpraisePost&sign=5a90da4cb4cab68342afcc7618c75662&version=d1bd83a33f1a841ab7fda32449746cc4";
        $data = array(
            'iscancel' => $iscancel,
            'uid' => $uid,
            'tid' => $tid,

        );

        $result = $this->urlpost($url, $data);
        echo $result;
    }

    /** #通过UID获取用户信息,
     * @param  String $uid
     * @return boolean
     */
    public function getUserInfoByUid()
    {
        $uid = $this->checkBbsId();
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=getUserInfoByUid";
        $data = array(
            "service" => "getUserInfoByUid",
            "sign" => "ebd9410ffaec8c980733da1fd3ae7371",
            "uid" => $uid,
            "version" => "d1bd83a33f1a841ab7fda32449746cc4",
        );
        $status = json_decode($this->urlpost($url, $data));
        if ($status->result) {

            $data = array(
                'status' => '404',
                'msg' => "获取用户资料失败",
            );

            echo json_encode($data);
            die;
        } else {
            $userinfo = $status->userinfo;
            $data = array(
                'status' => '200',
                'msg' => "获取用户资料成功",
                'username' => $userinfo->username,
                'province' => $userinfo->province,
                'city' => $userinfo->city,
                'area' => $userinfo->area,
                'uc_avatarUrl' => $userinfo->pic."&time=".time(),
                'sex' => $userinfo->gender
            );

            echo json_encode($data);
            die;
        }
    }
    #name 更新用户信息/个人资料
    #param sex 性别
    #param province 省份
    #param city 城市
    #param county 区
    public function xcxUpdateuserinfodetail()
    {
        $uid = $this->checkBbsId();
        $sex = intval($_REQUEST['sex']);
        $province = htmlentities($_REQUEST['province']);
        $city = htmlentities($_REQUEST['city']);
        $county = htmlentities($_REQUEST['county']);
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=xcxUpdateuserinfodetail";
        $data = array(
            "uid" => $uid,
            "sex" => $sex,
            "province" => $province,
            'city' => $city,
            'county' => $county,
            "service" => "xcxUpdateuserinfodetail",
            "sign" => "ebd9410ffaec8c980733da1fd3ae7371",
            "version" => "d1bd83a33f1a841ab7fda32449746cc4",
        );
        echo $this->urlpost($url, $data);

    }

    /** #通过UID更新用戶头像,
     * @param  String $uid
     * @param  file
     */
    public function xcxChangePic(){
        $uid = $this->checkBbsId();
        //类型判断
        $type = explode("/", $_FILES["file"]['type']);
        if (!in_array($type['1'], array('jpg', 'gif', 'png', 'jpeg'))) {
            $data = array(
                'status' => '404',
                'error' => '-1',
                'msg' => '未允许类型',
            );
            echo json_encode($data);
            die;
        }
        $size = 7000000;
        if ($_FILES["file"]['size'] > $size) {
            $data = array(
                'status' => '404',
                'error' => '-2',
                'msg' => '文件不能超过' . $size . "字节",
            );
            echo json_encode($data);
            die;
        }
        $fp = fopen($_FILES["file"]["tmp_name"], "rb");
        $buf = fread($fp, $_FILES["file"]["size"]);
        if (empty($_FILES) || empty($buf)) {
            $data = array(
                'status' => '301',
                'msg' => '上传失败',
                'url' => null
            );
            echo json_encode($data);
        }
        $data = array(
            'uid' => $uid,
            'tmp_name' => $_FILES["file"]['tmp_name'],
            'name' => $_FILES["file"]['name'],
            'size' => $_FILES["file"]['size'],
            'type' => $_FILES["file"]['type'],
            'buf' => base64_encode($buf),
        );
        $url = "http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=xcxChangePic&version=d1bd83a33f1a841ab7fda32449746cc4&service=xcxChangePic&sign=5a90da4cb4cab68342afcc7618c75662&version=d1bd83a33f1a841ab7fda32449746cc4";
        $status = $this->urlpost($url, $data);
        echo $status;


    }

    /** #我的帖子,
     * @param   $uid
     * @param
     */
    public function mythreadNew(){
        $uid = $this->checkBbsId();
        $pn = intval($_REQUEST['pn']);
        $url = 'http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=mythreadNew&pagesize=10&service=mythreadNew&sign=a95ea98ed9d89a20dadecac1a9718145&version=d1bd83a33f1a841ab7fda32449746cc4&page='.$pn .'&uid='.$uid;
        $status = json_decode($this->urlpost($url));
        $array = array();
        $array = $status->array;
        if(count($array)> 0 ){
            $data = array(
                'status' => '200',
                'msg' => "获取数据成功",
                'array'=>$array
            );

            echo json_encode($data);
            die;
        }else{
            $data = array(
                'status' => '404',
                'msg' => "没有更多数据",
                'array'=>$array
            );
            echo json_encode($data);
            die;
        }
    }

    /** #我的帖子删除,
     * @param   $uid
     * @param  $tid
     */

    public function delthread(){
        $uid = $this->checkBbsId();
        $tid = intval($_REQUEST['tid']);
        $url = 'http://bbs.chinapet.com/plugin.php?id=leepet_thread:api&action=delthread&service=delthread&sign=8ac36307a047a29b4381e9c952485027&&version=d1bd83a33f1a841ab7fda32449746cc4&tid='.$tid;
        $status = $this->urlpost($url);
        if($status){
            $data = array(
                'status' => '200',
                'msg' => "删除成功",
            );

            echo json_encode($data);
            die;
        }else{
            $data = array(
                'status' => '404',
                'msg' => "删除失败",
            );
            echo json_encode($data);
            die;
        }
    }

}