<?php
namespace app\user\controller;

use think\Validate;
use think\Db;
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
     * 用户申请加入项目
     */
    public function apply($id='')
    {
        if($id){
            $userId = bar_get_user_id();
            $msgQuery = Db::name("message");
            $projQuery = Db::name("project");
            $projBase = $projQuery->where('id',$id)->find();
            if($projBase){
                $leaderId = $projBase['leader_id'];
                $msgResult = $msgQuery->insert([
                    'from_id' => $userId,
                    'to_id' => $leaderId,
                    'proj_id' => $id,
                    'type' => 1
                ]);
                if(!$msgResult){
                    $this->error('发起申请失败，原因：无法向数据库添加申请数据');
                }
                $this->success("发起加入申请成功！");
            }
        }
    }

    /**
     * 邀请个人加入项目
     */
    public function invite($uid,$pid)
    {
        if($uid && $pid){
            $userId = bar_get_user_id();
            $msgQuery = Db::name("message");
            $msgResult = $msgQuery->insert([
                'from_id' => $userId,
                'to_id' => $uid,
                'proj_id' => $pid,
                'type' => 2
            ]);
            if(!$msgResult){
                $this->error("发起邀请失败，原因：无法向数据库中插入邀请数据");
            }$this->success("发起邀请成功！");
        }else{
            $this->error("请求参数错误");
        }
    }

    /**
     * 接受邀请或申请
     */
    public function accept($id=''){
        if($id){
            $userId = bar_get_user_id();
            $msgQuery = Db::name("message");
            $userProjQuery = Db::name("user_proj");
            $msg = $msgQuery->where('id',$id)->find();
            if(!$msg){
                $this->error("不存在该条申请/邀请信息");
            }
            $projId = $msg['proj_id'];
            if($msg['type'] == 1){
                $addUserId = $msg['from_id'];
            }elseif($msg['type'] == 2){
                $addUserId = $msg['to_id'];
            }
            if($msg['to_id'] == $userId){
                $msgResult = $msgQuery->where('id',$id)->update(['has_handle' => 1]);
                $userProjResult = $userProjQuery->insert(['proj_id'=>$projId,'user_id'=>$addUserId]); 
                if($msgResult && $userProjResult){
                    $this->success("接受成功！您可以在个人中心->我的项目里查看有关详细信息");
                }else{
                    $this->error("接受失败！数据库内部错误，如果您一直遇到此问题，请尽快反馈给我们");
                }
            }else{
                $this->error("操作授权错误,请不要搞事，谢谢合作");
            }
        }else{
            $this->error("不受理的访问");
        }
    }
    
    /**
     * 测试
     */
    public function test(){
        print_r(session('user'));
        return ;
    }
}