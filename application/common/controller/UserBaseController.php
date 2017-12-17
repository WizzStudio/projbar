<?php
namespace app\common\controller;

use app\common\controller\BaseController;
use think\Db;

class UserBaseController extends BaseController
{   
    public function _initialize()
    {   
        parent::_initialize();
        $userId = bar_get_user_id();
        if(!$userId){
            $get = $this->request->get();
            if(isset($get['token'])){
                $userQuery = Db::name("user");
                $result = Db::name("user_token")->where('token',$get['token'])->find();
                if($result['expire_time'] < time()){
                    $this->error('token已经过期，请重新登录后再进行操作','user/public/login');
                }

                $getUser = $userQuery->where('id',$result['user_id'])->find();
                session('user', $getUser);

                $navRight = "<li><a href=".url('user/profile/center').">个人中心</a></li>
                <li><a href=".url('user/profile/logout').">注销</a></li>";
                $this->assign('navRight',$navRight);
                
                $data = [
                    'last_login_time' => time(),
                    'last_login_ip' => get_client_ip(0, true)
                ];
                $userQuery->where('id', $getUser['id'])->update($data);
            }else{
                $this->error('您还未登录，请登录后再进行操作噢~','user/public/login');
            }
        }
    }
}