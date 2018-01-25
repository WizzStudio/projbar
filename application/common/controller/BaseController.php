<?php
namespace app\common\controller;

use think\Controller;

class BaseController extends Controller
{   
    private $navRight = '';
    private $userId = '';

    public function _initialize()
    {
        $userId = bar_get_user_id();
        if(!$userId){
            $navRight = "<li><a href=".url('user/public/login').">登录</a></li>
            <li><a href=".url('user/public/register').">注册</a></li>";
        }else{
            $navRight = "<li><a href=".url('user/profile/center')."><span class='glyphicon glyphicon-user'></span>&nbsp;个人中心</a></li>
            <li><a id='logout'><span class='glyphicon glyphicon-off'></span>&nbsp;注销</a></li>";
        }
        $this->navRight = $navRight;
        $this->assign('navRight',$navRight);
    }

    public function getNavRight()
    {
        return $this->navRight;
    }
}