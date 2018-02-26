<?php
namespace app\api\controller;

use think\Validate;
use think\Db;
use app\common\controller\BaseController;

class ProjectController extends BaseController
{   
    public function index(){}

    /**
     * 查看单个项目信息
     */
    public function read($id){
        $projQuery = Db::name("project");
        $result = $projQuery->where('id',$id)->find();
        $result['need'] = json_decode($result['need']);
        return json($result);
    }
        
    /**
     * 添加项目
     */
    public function save(){
        $data = $this->request->post();
        return json($data);
    }

    /**
     * 更新项目信息
     */
    public function update($id){
        $data = $this->request->param();
        return json($data);
    }

    /**
     * 删除项目
     */
    public function delete($id){
        return json("hello,delete");
    }
    
}