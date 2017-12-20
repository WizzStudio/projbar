<?php

namespace app\common\model;

use think\Db;
use think\Model;

class User extends Model
{   
    /**
     * 邮箱注册
     */
    public function registerEmail($user)
    {
        $userQuery = Db::name("user");
        $resultEmail = $userQuery->where('email', $user['account'])->find();
        if(!empty($resultEmail)) return 1;
        $resultUserName = $userQuery->where('username', $user['username'])->find();
        if(!empty($resultUserName)) return 2;

        $data = [
            'username' => $user['username'],
            'email' => $user['account'],
            'password' => bar_password($user['password']),
            'mobile' => '',
            'nickname' => $user['username'],
            'last_login_ip' => get_client_ip(0, true),
            'create_time' => time(),
            'last_login_time' => time(),
            'status' => 1
        ];

        $result = $userQuery->insert($data);
        return 0;
    }

    /**
     * 邮箱登录
     */
    public function doEmail($user)
    {
        $userQuery= Db::name("user");

        $result = $userQuery->where('email',$user['account'])->find();

        if(!empty($result)){
            $compareResult = bar_compare_password($user['password'], $result['password']);
            if($compareResult){
                if($result['status'] == 0){
                    return 3;
                }

                session('user', $result);
                $data = [
                    'last_login_time' => time(),
                    'last_login_ip' => get_client_ip(0, true)
                ];
                $userQuery->where('id', $result['id'])->update($data);
                return 0;
            }else{
                return 2;
            }
        }else{
            return 1;
        }
    }

    /**
     * 用户名登录
     */
    public function doName($user)
    {
        $userQuery = Db::name("user");

        $result = $userQuery->where('username', $user['account'])->find();

        if(!empty($result)){
            $compareResult = bar_compare_password($user['password'], $result['password']);
            if($compareResult){
                if($result['status'] == 0){
                    return 3;
                }
                session('user', $result);
                $data = [
                    'last_login_time' => time(),
                    'last_login_ip' => get_client_ip(0, true)
                ];
                $userQuery->where('id', $result['id'])->update($data);
                return 0;
            }else{
                return 2;
            }
        }else{
            return 1;
        }
    }

    /**
     * 个人资料修改提交
     */
    public function doBaseEdit($user)
    {
        $userId = bar_get_user_id();
        $data['mobile'] = $user['mobile'];
        $data['nickname'] = $user['nickname'];
        $data['qq'] = $user['qq'];
        $data['sex'] = $user['sex'];
        $data['extra'] = $user['extra'];
        $userQuery = Db::name("user");
        if($userQuery->where('id', $userId)->update($data)) {
            $userInfo = $userQuery->where('id', $userId)->find();
            bar_update_current_user($userInfo);
            return 1;
        }else{
            return 0;
        }
    }

    /**
     * 修改密码
     */
    public function changePass($oldPass,$newPass)
    {
        $userId = bar_get_user_id();
        $userQuery = Db::name("user");
        $realPass = $userQuery->where('id',$userId)->value('password');
        if(bar_password($oldPass) != $realPass) return 1;
        $resultPass = bar_password($newPass);        
        $data = [
            'password' => $resultPass,
            'update_time' => time()
        ];
        $update = $userQuery
            ->where('id',$userId)
            ->update($data);
        if($update){
            return 0;
        }else{
            return 2;
        }
    }
}