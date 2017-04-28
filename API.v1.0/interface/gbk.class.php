<?php
class GBK {
  public static function unserialize($s) {
    $s = preg_replace_callback('/s:\d+:"([^"]+)";/',
        function($r) {
            $n = strlen($r[1]);
            return "s:$n:\"$r[1]\";";
        },
        $s
        );
    return unserialize($s);
  }
 
  public static function json_encode($s, $charset='gbk') {
    if($charset == 'utf-8') return json_encode($s);
    $s = serialize($s);
    $s = gbk::unserialize(iconv($charset, 'utf-8', $s));
    return preg_replace_callback('/[\\\]u(\w{4})/',
        function($r) use ($charset) {
            return iconv('ucs-2', $charset, pack('H4', $r[1]));
        },
        json_encode($s)
        );
  }
 
  public static function json_decode($s, $assoc=0, $charset='gbk') {
    if(json_encode(json_decode($s)) != $s) $s = iconv('gbk', 'utf-8', $s);
    $t = json_decode($s, $assoc);
    if($charset == 'uft-8') return $t;
    return gbk::unserialize(iconv('utf-8', $charset, serialize($t)));
  }
 
  public static function preg_replace($pattern, $replacement, $subject, $limit=-1) {
    $pattern = iconv('gbk', 'utf-8', $pattern);
    $replacement = iconv('gbk', 'utf-8', $replacement);
    $subject = iconv('gbk', 'utf-8', $subject);
    $t = preg_replace($pattern, $replacement, $subject, $limit=-1);
    return iconv('utf-8', 'gbk', $t);
  }
  public static function preg_match($pattern, $subject, &$matches=array(), $flags=0) {
    self::toutf8($pattern);
    self::toutf8($subject);
    $n = preg_match($pattern, $subject, $matches, $flags);
    if($matches) self::togbk($matches);
    return $n;
  }
  static function toutf8(&$str) {
    if(is_array($str)) foreach($str as &$s) return self::toutf8($s);
    $str = iconv('gbk', 'utf-8', $str);
  }
  static function togbk(&$str) {
    if(is_array($str)) foreach($str as &$s) return self::togbk($s);
    $str = iconv('utf-8', 'gbk', $str);
  }
}