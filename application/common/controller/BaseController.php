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
            $navRight = "<li><a href=".url('user/public/login').">Login</a></li>
            <li><a href=".url('user/public/register').">Register</a></li>";
        }else{
            $navRight = "<li><a href=".url('user/profile/center').">Personal Center</a></li>
            <li><a href=".url('user/profile/logout').">LogOut</a></li>";
        }
        $this->navRight = $navRight;
        $this->assign('navRight',$navRight);
    }

    public function getNavRight()
    {
        return $this->navRight;
    }
}