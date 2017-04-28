<?php
/**
 * Created by PhpStorm.
 * User: lining
 * Date: 15/3/3
 * Time: 上午11:11
 */
class test{

    public function test(){
        echo "test";
    }

    public function getKey()
    {
        session_set_cookie_params(120);
        Session_start();
        $k = time();
        $key = base64_encode(base64_encode($k));
        $_SESSION["k"];
        $_SESSION["key"];
        var_dump($_SESSION["k"]);exit;
        //printJson(1,array('appKey'=>$key),'');

    }

    public function testParam()
    {
        $a = $_REQUEST['ac'];
        if($this->inject_check($a)){
            echo 111;
        }else{
            echo 222;
        }

    }

    public function testAes()
    {
         if($_REQUEST['s'])
         {
             $aes = new AESMcrypt($bit = 128, $key = 'abcdef1234567890', $iv = '0987654321fedcba', $mode = 'cbc');
             $value = $aes->decrypt($_REQUEST['s']);
             $params ='';
         }
    }


}
class AESMcrypt {
    public $iv = null;
    public $key = null;
    public $bit = 128;
    private $cipher;
    public function __construct($bit, $key, $iv, $mode) {
        if(empty($bit) || empty($key) || empty($iv) || empty($mode))
            return NULL;
        $this->bit = $bit;
        $this->key = $key;
        $this->iv = $iv;
        $this->mode = $mode;
        switch($this->bit) {
            case 192:$this->cipher = MCRYPT_RIJNDAEL_192; break;
            case 256:$this->cipher = MCRYPT_RIJNDAEL_256; break;
            default: $this->cipher = MCRYPT_RIJNDAEL_128;
        }
        switch($this->mode) {
            case 'ecb':$this->mode = MCRYPT_MODE_ECB; break;
            case 'cfb':$this->mode = MCRYPT_MODE_CFB; break;
            case 'ofb':$this->mode = MCRYPT_MODE_OFB; break;
            case 'nofb':$this->mode = MCRYPT_MODE_NOFB; break;
            default: $this->mode = MCRYPT_MODE_CBC;
        }
    }
    public function encrypt($data) {
        $data = base64_encode(mcrypt_encrypt( $this->cipher, $this->key, $data, $this->mode, $this->iv));
        return $data;
    }
    public function decrypt($data) {
        $data = mcrypt_decrypt( $this->cipher, $this->key, base64_decode($data), $this->mode, $this->iv);
//        $data = rtrim(rtrim($data), "..");
        return $data;
    }
}