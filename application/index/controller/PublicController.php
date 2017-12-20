<?php
namespace app\index\controller;

use app\common\controller\BaseController;

class PublicController extends BaseController
{
    /**
     * 关于项慕吧
     */
    public function about()
    {
        return $this->fetch();
    }

    /**
     * 联系我们
     */
    public function contact()
    {
        return $this->fetch();
    }
}