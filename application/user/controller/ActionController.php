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

    /**
     * 发布项目处理
     */
    public function release_handle()
    {
        $post = $this->request->post();
        $roleNumber = count($post['role']);
        $userId = bar_get_user_id();
        for($i=0;$i<$roleNumber;$i++){
            $roleId = $post['role'][$i];
            $data[$roleId][$post['skill1'][$i]] = $post['level1'][$i];
            $data[$roleId][$post['skill2'][$i]] = $post['level2'][$i];
            $data[$roleId][$post['skill3'][$i]] = $post['level3'][$i];
        }
        $tags = $post['tags'];

        $baseInfo['name'] = $post['name'];
        $baseInfo['cate_id'] = $post['category'];
        $baseInfo['email'] = $post['email'];
        $baseInfo['intro'] = $post['intro'];
        $baseInfo['leader_id'] = $userId;

        $projectModel = new Project();
        $log = $projectModel->doRelease($data,$tags,$baseInfo,$roleNumber);
        switch($log){
            case 0:
                $this->success('项目发布成功!','/');
                break;
            case 1:
                $this->error('项目基本信息添加失败！');
                break;
            case 2:
                $this->error('项目标签添加失败！');
                break;
            case 3:
                $this->error('项目需求角色信息添加失败！');
                break;
            default:
                $this->error('未受理的请求');
        }
    }

    /**
     * 前端业务逻辑测试
     */
    public function test()
    {
        return $this->fetch();
    }
}