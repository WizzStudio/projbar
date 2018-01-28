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
use think\Request;

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
function bar_get_user_id()
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
    $maxCount = 6;
    $find = $verifyCodeQuery->where('account', $account)->order('id','desc')->find();
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
 * 更新手机或邮箱验证码发送日志
 * @param string $account 手机或邮箱
 * @param string $code 验证码
 * @param int $expireTime 过期时间
 * @return boolean
 */
function bar_verify_code_log($account, $code, $expireTime = 0)
{
    $currentTime           = time();
    $expireTime            = $expireTime > $currentTime ? $expireTime : $currentTime + 30 * 60;
    $verifyCodeQuery = Db::name('verify_code');
    $find = $verifyCodeQuery->where('account', $account)->find();
    if ($find) {
        $todayStartTime = strtotime(date("Y-m-d"));//当天0点
        if ($find['send_time'] <= $todayStartTime) {
            $count = 1;
        } else {
            $count = $find['count'] + 1;//????
        }
        $result = $verifyCodeQuery
            ->where('account', $account)
            ->update([
                'send_time'   => $currentTime,
                'expire_time' => $expireTime,
                'code'        => $code,
                'count'       => $count
            ]);
    } else {
        $result = $verifyCodeQuery
            ->insert([
                'account'     => $account,
                'send_time'   => $currentTime,
                'code'        => $code,
                'count'       => 1,
                'expire_time' => $expireTime
            ]);
    }

    return $result;
}

/**
 * 手机或邮箱验证码检查，验证完后销毁验证码增加安全性,返回true验证码正确，false验证码错误
 * @param string $account 手机或邮箱
 * @param string $code 验证码
 * @param boolean $clear 是否验证后销毁验证码
 * @return string  错误消息,空字符串代码验证码正确
 */
function bar_check_verify_code($account, $code, $clear = false)
{
    $verifyCodeQuery = Db::name('verify_code');
    $findVerifyCode  = $verifyCodeQuery->where('account', $account)->find();

    if ($findVerifyCode) {
        if ($findVerifyCode['expire_time'] > time()) {

            if ($code == $findVerifyCode['code']) {
                if ($clear) {
                    $verifyCodeQuery->where('account', $account)->update(['code' => '']);
                }
            } else {
                return "验证码不正确!";
            }
        } else {
            return "验证码已经过期,请先获取验证码!";
        }

    } else {
        return "请先获取验证码!";
    }

    return "";
}

/**
 * 清除某个手机或邮箱的数字验证码,一般在验证码验证正确完成后
 * @param string $account 手机或邮箱
 * @return boolean true：手机验证码正确，false：手机验证码错误
 */
function bar_clear_verify_code($account)
{
    $verifyCodeQuery = Db::name('verify_code');
    $verifyCodeQuery->where('account', $account)->update(['code' => '']);
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

            cache('bar_options_' . $key, $optionValue);
        }
    }

    $getOption[$key] = $optionValue;

    return $optionValue;
}

/**
 * 检查用户动作是否可以触发邮件通知，若可以则获取邮件内容，若禁止则返回false
 * @param int $userId 执行动作的用户id
 * @param int $type 动作主被动(1主动/2被动)
 * @return string
 */
function bar_get_action_email($userId,$fromName,$projId,$type)
{
    if(!$userId) return false;
    $emailQuery = Db::name("email");
    $currentTime = time();
    $maxCount = 6;
    $result = false;
    $find = $emailQuery->where('user_id',$userId)->find();
    if($type == 1 || $type == 2){
        if(empty($find)){
            $result = true;
        }else{
            $sendTime = $find['send_time'];
            //获取今日开始时间，没毛病
            $todayStartTime = strtotime(date('Y-m-d',$currentTime));
            if($sendTime < $todayStartTime){
                $result = true;
            }else if($find['count'] < $maxCount){
                $result = true;
            }
        }
    }else if($type == 3 || $type == 4){
        $result = true;
    }

    if($result){
        $projQuery = Db::name("project");
        $projName = $projQuery->where('id',$projId)->value('name');       
        switch($type){
            case 1:
                // $result = "【项慕吧】".$fromName."申请加入您的项目：".$projName."，请登录<a href='http://www.projbar.cn'>项慕吧PROJBar</a>，在个人中心查看详情。";
                $result = "【项慕吧】".$fromName."申请加入您的项目：".$projName."，请在项慕吧->个人中心查看详情，为了您的账户安全，请勿将该邮件转发给他人，";                
                break;
            case 2:
                $result = "【项慕吧】".$fromName."邀请您加入项目：".$projName."，请在项慕吧->个人中心查看详情，为了您的账户安全，请勿将该邮件转发给他人，";
                break;
            case 3:
                $result = "【项慕吧】".$fromName."同意了您的请求，相关项目：".$projName."，请在项慕吧->个人中心查看详情，为了您的账户安全，请勿将该邮件转发给他人，";
                break;
            case 4:
                $result = "【项慕吧】".$fromName."拒绝了您的请求，相关项目：".$projName."，请在项慕吧->个人中心查看详情，为了您的账户安全，请勿将该邮件转发给他人，";
                break;
        }
    }
    return $result;
}

/**
 * 更新用户动作邮件发送日志
 * @param string $user_id 执行动作用户id
 * @return boolean
 */
function bar_action_email_log($userId,$type)
{
    if($userId){
        $currentTime = time();
        $emailQuery = Db::name("email");
        $find = $emailQuery->where('user_id',$userId)->where('type',$type)->find();
        if($find){
            $todayStartTime = strtotime(date('Y-m-d'));
            if($find['send_time'] <= $todayStartTime){
                $count = 1;
            }else{
                $count = $find['count'] + 1;
            }
            $result = $emailQuery
                ->where('user_id',$userId)
                ->update([
                    'count' => $count,
                    'send_time' => $currentTime
                ]);
        }else{
            $result = $emailQuery
                ->insert([
                    'user_id' => $userId,
                    'type' => $type,
                    'count' => 1,
                    'send_time' => $currentTime
                ]);
        }

        return $result;
    }else{
        return false;
    }
}

/**
  * 发送邮件
  * @param string $address 收件人邮箱
  * @param string $subject 邮件标题
  * @param string $message 邮件内容
  * @param string $token 用户直接进入个人中心的token
  * @return array
  * 返回格式
  * array(
  *   "error" => 0|1, //1代表出错
  *   "message" => "出错信息"
  * );
  */
  function bar_send_email($address,$subject,$message,$token='')
  {   
      $mail = new \PHPMailer\PHPMailer\PHPMailer();
      $mail->IsSMTP();
      $mail->IsHTML(true);
      $mail->CharSet = 'UTF-8';
      //添加收件人地址，可以多次使用来添加多个收件人
      $mail->AddAddress($address);
      if($token){
        $mail->Body = $message."(若使用QQ邮箱，可能存在过滤，请复制该链接到浏览器或自行登录项慕吧),两小时内有效,个人中心跳转链接：<a href='http://www.projbar.cn/user/profile/center?token=$token'>http://www.projbar.cn/user/profile/center?token=$token</a>";        
      }else{
        $mail->Body = $message;
        
      }
      $mail->From = "wxjackie@wxj.projbar.cn";
      $mail->FromName = "项慕吧";
      $mail->Subject = $subject;
      $mail->Host = 'smtpdm.aliyun.com';
      $mail->Port = 80;
      $mail->SMTPAuth = true;
      $mail->SMTPAutoTLS = false;
      $mail->Timeout = 10;
      $smtpSetting = bar_get_option("smtp_setting");
      $mail->Username = $smtpSetting['username'];
      $mail->Password = $smtpSetting['password'];
      if(!$mail->Send()){
          $mailError = $mail->ErrorInfo;
          return ['error'=>1,"msg"=>$mailError];
      }else{
          return ['error'=>0,"msg"=>"success"];
      }
  }

/**
 * 判断是否允许开放注册
 */
function bar_is_open_register()
{
    // $cmfSettings = cmf_get_option('cmf_settings');
    // return empty($cmfSettings['open_registration']) ? false : true;
    $is_open_register = 0;
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

/**
 * 获取网站根目录
 * @return string 网站根目录
 */
function bar_get_root()
{
    $request = Request::instance();
    $root    = $request->root();
    return $root;
}

/**
 * 测试邮件发送
 */
function test_send($account,$subject,$message){
    $mail = new \PHPMailer\PHPMailer\PHPMailer();
    $mail->IsSMTP();
    $mail->IsHTML(true);
    $mail->Charset = 'UTF-8';
    $mail->Host = 'smtpdm.aliyun.com';
    $mail->Port = 80;
    $mail->Username = $smtpSetting['username'];
    $mail->Password = $smtpSetting['password'];
    if($mail->Send()){
        return 0;
    }else{
        return 1;
    }
}

/**
 * 判断用户今日是否已经申请加入项目/邀请该用户
 * @param int $fromId 消息发送方ID
 * @param int $projId 相关项目
 * @param int $type 1申请/2邀请
 * @param int 1今日已申请/邀请,0未
 */
function bar_has_action_today($fromId,$toId,$projId,$type)
{   
    $result = 0;
    $msgFind = Db::name("message")
        ->where(['from_id'=>$fromId,'to_id'=>$toId,'proj_id'=>$projId,'type'=>$type])
        ->order('id','desc')
        ->find();
    if($msgFind){
        $currentTime = time();
        $sendTime = $msgFind['send_time'];
        $todayStartTime = strtotime(date('Y-m-d',$currentTime));
        if($sendTime > $todayStartTime){
            $result = 1;
        }
    }
    return $result;
}

/**
 * 发布项目时获取项目背景图(1-9)
 * @param $id 图片id
 * @return string 图片相对路径
 */
function bar_get_proj_image()
{   
    $maxNum = 7;
    $prefix = 'new';
    $imageId = rand(1,$maxNum);
    $beforeUrl = '/static/images/proj_images';
    $result = $beforeUrl.'/'.$prefix.$imageId.'.jpg';
    return $result;
}

/**
 * 生成get参数中的token
 */
function bar_get_user_token($uid)
{   
    $userTokenQuery = Db::name("user_token");
    $data = [];
    $currentTime = time();
    $expireTime = $currentTime + 3600*3;
    $data['token'] = md5(uniqid('one')) . md5(uniqid('two'));
    $data['user_id'] = $uid;
    $data['expire_time'] = $expireTime;
    $exist = $userTokenQuery->where('user_id',$uid)->find();
    if($exist){
        $data['update_time'] = $currentTime;
        $result = $userTokenQuery->where('user_id',$uid)->update($data);
    }else{
        $data['create_time'] = $currentTime;
        $result = $userTokenQuery->insert($data);        
    }
    if($result){
        return $data['token'];
    }else{
        return false;
    }
}


/**
 * 获取修改密码授权
 * @param int $userId
 */
function bar_get_change_pass_auth($userId)
{

}

/**
 * 获取发布项目授权
 * @param int $userId
 */
function bar_get_release_auth($userId)
{
    $projQuery = Db::name("project");
    $currentTime = time();
    $todayStartTime = strtotime(date('Y-m-d',$currentTime));
    $projCount = $projQuery
        ->where('leader_id',$userId)
        ->where('create_time','>',$todayStartTime)
        ->count();
    if($projCount < 5){
        return 1;
    }else{
        return 0;
    }
}

/**
 * TODO
 * 发布项目流程数据库失败后回滚
 * @param int $projId
 */
function bar_release_rollback($projId)
{   
    $errorLog = 0;
    $refArray = ['project','proj_tag','proj_skill','user_proj'];
    foreach($refArray as $ref){
        $query = Db::name($ref);
        if($query->where('id',$projId)->find()){
            $result = $query->where('id',$projId)->delete();
            if(!$result) $errorLog++;
        }
    }
    return $errorLog;
}

