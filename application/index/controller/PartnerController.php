<?php
namespace app\index\controller;

use think\Validate;
use think\Db;
use app\common\controller\BaseController;

class PartnerController extends BaseController
{
    /**
     * 个人列表
     */
    public function lists()
    {
        $userQuery = Db::name("user");
        $userTagQuery = Db::name("user_tag");
        $tagQuery = Db::name("tag");
        $roleQuery = Db::name("role");
        $tags = $tagQuery->select();
        $roles = $roleQuery->select();
        // 按照信息完整度排序
        $userBaseList = $userQuery
            ->field('id,username,sex,nickname,role')
            ->where('status',1)
            ->order(['list_order'=>'desc','last_login_time'=>'desc'])
            ->paginate(6);
        $userList = bar_user_list_splice($userBaseList);

        $this->assign([
            "userBaseList" => $userBaseList,
            "userList" => $userList,
            "roles" => $roles,
            "tags" => $tags,
        ]);
        return $this->fetch();
    }

    /**
     * 查看个人的详细信息
     */
    public function view($id='')
    {
        if($id){
            $status = 0;
            $userId = bar_get_user_id();
            if($userId == $id) $status = 1;
            $userQuery = Db::name("user");
            $userTagQuery = Db::name("user_tag");
            $projQuery = Db::name("project");
            $userBase = $userQuery->where("id",$id)->find();
            $userBase['role'] = json_decode($userBase['role'],true);
            $userBase['role']['role_name'] = Db::name("role")->where('id',$userBase['role']['type'])->value('name');
            
            $tags = $userTagQuery
            ->alias('a')
            ->field('b.*')
            ->where(['user_id' => $id])
            ->join('__TAG__ b','a.tag_id=b.id')
            ->select();

            $resultMyProjects = [];
            $myProjects = $projQuery
                ->alias('a')
                ->join('__CATEGORY__ b','a.cate_id=b.id')
                ->where('leader_id',$userId)
                ->field('a.id,a.cate_id,a.name,b.name as cate_name')
                ->select();
            foreach($myProjects as $project){
                //查询每个项目今日是否已经邀请该用户
                $project['has_invite_today'] = bar_has_action_today($userId,$id,$project['id'],2);
                $resultMyProjects[] = $project;
            }
            
            $this->assign([
                'status' => $status,
                'user' => $userBase,
                'tags' => $tags,
                'myProjects' => $resultMyProjects
            ]);
            return $this->fetch();

        }else{
            $this->error("未指定个人id");
        }
    }

    /**
     * 获得筛选后的人才列表
     */
    public function filter()
    {
        $getData = $this->request->get();
        $rid = isset($getData['role'])?$getData['role']:'';        
        $tid = isset($getData['tag'])?$getData['tag']:'';

        $userQuery = Db::name("user");
        $userTagQuery = Db::name("user_tag");
        $tagQuery = Db::name("tag");
        $roleQuery = Db::name("role");
        $allTags = $tagQuery->select();
        $allRoles = $roleQuery->select();

        if(!$rid && !$tid){
            return $this->redirect(url('index/partner/lists'));
        }else if($rid && !$tid){
            $userIds = $userQuery
                ->where('role_id',$rid)
                ->column('id');
            $userList = $userQuery->where('id','in',$userIds)->select();
        }else if(!$rid && $tid){
            $userIds = $userTagQuery
                ->where('tag_id',$tid)
                ->distinct('user_id')
                ->column('user_id');
        }else if($rid && $tid){
            $userIds = $userQuery
                ->alias('a')
                ->join('__USER_TAG__ b','a.id=b.user_id')
                ->where('a.role_id',$rid)
                ->where('b.tag_id',$tid)
                ->distinct('a.id')
                ->column('a.id');
        }
        $userBaseList = $userQuery
            ->where('id','in',$userIds)
            ->order(['list_order'=>'desc','last_login_time'=>'desc'])
            ->paginate(6);
        $userList = bar_user_list_splice($userBaseList);

        $this->assign([
            'userBaseList' => $userBaseList,
            'userList' => $userList,
            'allRoles' => $allRoles,
            'allTags' => $allTags,
            'tid' => $tid,
            'rid' => $rid,
        ]);

        return $this->fetch();
    }
}