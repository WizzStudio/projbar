<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\Db;
use think\Url;
use think\Route;
// 应用公共文件

//设置插件入口路由


/**
 * 判断用户是否登录
 */
function bar_is_user_login()
{
    $sessionUser = session('user');
    return !empty($sessionUser);
}

/**
 * 获取当前登录的用户信息，未登录返回false
 */
function bar_get_current_user()
{
    $sessionUser = session('user');
    if(!empty($sessionUser)){
        return $sessionUser;
    }else{
        return false;
    }
}

/**
 * 更新当前登录前台用户的信息
 * @param array $user 前台用户的信息
 */
function bar_update_current_user($user)
{
    session('user', $user);
}

/**
 * 获取当前登录的用户id
 * @return int
 */
function bar_get_current_user_id()
{
    $sessionUserId = session('user.id');
    if (empty($sessionUserId)) {
        return 0;
    }
    return $sessionUserId;
}

/**
 * 检查用户是否可以发送验证码并生成验证码
 * @param string $account 手机或邮箱
 * @param string $验证码位数 支持4/6/8
 * @return string 验证码
 */
function bar_get_verify_code($account, $length = 6)
{
    if(empty($account)) return false;
    $verifyCodeQuery = Db::name('verify_code');
    $currentTime = time();
    $maxCount = 8;
    $find = $verifyCodeQuery->where('account', $account)->find();
    $result = false;
    if(empty($find)){
        $result = true;
    }else{
        $sendTime = $find['send_time'];
        $todayStartTime = strtotime(date('Y-m-d', $currentTime));
        if($sendTime < $todayStartTime){
            $result = true;
        }else if($find['count'] < $maxCount){
            $result = true;
        }
    }

    if($result){
        switch($length){
            case 4:
                $result = rand(1000,9999);
                break;
            case 6:
                $result = rand(100000,999999);
                break;
            case 8:
                $result = rand(10000000, 99999999);
                break;
            default:
                $result = rand(100000,999999);
        }
    }

    return $result;
}

/**
  * 发送邮件
  * @param string address 收件人邮箱
  * @param string $subject 邮件标题
  * @param string $message 邮件内容
  * @return array
  * 返回格式
  * array(
  *   "error" => 0|1, //1代表出错
  *   "message" => "出错信息"
  * );
  */
function bar_send_email($address, $subject, $message)
{
    $mail = new \PHPMailer();
    $mail->IsSMTP();
    $mail->IsHTML(true);
    $mail->Charset = 'UTF-8';
    //添加收件人地址，可以多次使用来添加多个收件人
    $mail->AddAddress($address);
    $mail->Subject = $subject;
    $mail->Body = $message;
}

/**
 * 获取系统配置
 * @param 配置名
 * @return array
 */
function bar_get_option($key)
{
    if(!is_string($key) || empty($key)){
        return [];
    }

    static $getOption;

    if(empty($getOption)){
        $getOption = [];
    }else{
        if(!empty($getOption[$key])){
            return $getOption[$key];
        }
    }

    $optionValue = cache('bar_options_' . $key);

    if(empty($optionValue)){
        $optionValue = Db::name('option')->where('option_name', $key)->value('option_value');
        if(!empty($optionValue)){
            $optionValue = json_decode($optionValue, true);

            cache('stars_options_' . $key, $optionValue);
        }
    }

    $getOption[$key] = $optionValue;

    return $optionValue;
}

/**
 * 判断是否允许开放注册
 */
function bar_is_open_register()
{
    // $cmfSettings = cmf_get_option('cmf_settings');
    // return empty($cmfSettings['open_registration']) ? false : true;
    $is_open_register = 1;
    return $is_open_register > 0 ? true : false;
}

/**
 * 密码加密存储
 * @param string $pass 原始密码
 * @param string $key 加密字符串
 */
function bar_password($pass, $key = 'mybar')
{
    $result = "##".sha1(md5($pass).$key);
    return $result;
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
 * @return string
 */
function get_client_ip($type = 0, $adv = false)
{
    return request()->ip($type, $adv);
}

/**
 * 密码对比
 * @param string $passInput 输入的密码
 * @param string $passReal 数据库中的密码
 * @return boolean
 */
function bar_compare_password($passInput, $passReal)
{   
    return bar_password($passInput) == $passReal;
}