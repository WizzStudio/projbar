<?php
namespace app\index\controller;

use app\common\controller\BaseController;

class IndexController extends BaseController
{
    public function index()
    {
        return $this->fetch();
    }

    /**
     * 测试
     */
    public function test()
    {
        return $this->fetch();
    }
}
