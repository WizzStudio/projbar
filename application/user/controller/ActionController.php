<?php
namespace app\user\controller;

use think\Validate;
use app\common\model\Project;
use app\common\controller\UserBaseController;

class ActionController extends UserBaseController
{
    /**
     * 发布项目
     */
    public function release()
    {
        return $this->fetch();
    }
}