<?php
namespace app\common\controller;

use app\common\controller\BaseController;

class UserBaseController extends BaseController
{   
    public function _initialize()
    {   
        parent::_initialize();
        $userId = bar_get_user_id();
        if(!$userId){
            $this->error('您还未登录，请登录后再进行操作噢~','user/public/login');
        }
    }
}