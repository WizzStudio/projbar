<?php

namespace app\common\controller;

use think\Controller;

class BaseController extends Controller
{
    protected $navRight = '';
    protected $userId = '';
    
    public function _initialize()
    {
        $userId = bar_get_user_id();
        if(!$userId){
            $navRight = <<<EOF
            <li><a href="{:url('user/public/login')}">Login</a></li>
            <li><a href="{:url('user/public/register')}>">Register</a></li>
EOF;
        }else{
            $navRight = "<li><a href=".url('user/profile/center').">Personal Center</a></li>";
        }
        $this->navRight = $navRight;
        $this->assign('navRight', $navRight);
    }

    public function getNavRight()
    {
        return $this->navRight;
    }
}