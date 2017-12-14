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
        $userSkillQuery = Db::name("user_skill");
        $tagQuery = Db::name("tag");
        $roleQuery = Db::name("role");
        $tags = $tagQuery->select();
        $roles = $roleQuery->select();
        // TODO 按照信息完整度排序
        $userBase = $userQuery->field('id,username,sex,nickname')->order('id','desc')->paginate(3);
        $userList = [];
        foreach($userBase as $user){
            if(!$user['nickname']){
                $user['nickname'] = $user['username'];
            }
            $user['tags'] = [];
            $user['role'] = [];
            $tagSelect = $userTagQuery
                ->alias('a')
                ->field('b.id,b.name')
                ->where(['user_id' => $user['id']])
                ->join('__TAG__ b','a.tag_id=b.id')
                ->select();
            foreach($tagSelect as $tag){
                $user['tags'][] = $tag['name'];
            }
            $skillSelect = $userSkillQuery
                ->where(['user_id' => $user['id']])
                ->select();
            foreach($skillSelect as $skill){
                $user['role'][$skill['role_id']][$skill['name']] = $skill['level'];
            }
            array_pop($user['role']);
            $userList[] = $user;
        }
        // print_r($userList);
        // return ;
        $this->assign([
            "userBase" => $userBase,
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
            $userSkillQuery = Db::name("user_skill");
            $expQuery = Db::name("exp");
            $projQuery = Db::name("project");
            $userBase = $userQuery->where("id",$id)->find();
            
            $roleInfo = [];
            $exp = '';

            $userSkillList = $userSkillQuery->where("user_id",$id)->select();
            if(!empty($userSkillList)){
                foreach($userSkillList as $skill){
                    $roleIds[] = $skill['role_id'];
                }
                $roleIds = array_unique($roleIds);
                foreach($roleIds as $roleId){
                    foreach($userSkillList as $skill){
                        if($skill['role_id'] == $roleId){
                            $roleInfo[$roleId][] = $skill;
                        }
                    }
                }
            }
            $tags = $userTagQuery
            ->alias('a')
            ->field('b.*')
            ->where(['user_id' => $id])
            ->join('__TAG__ b','a.tag_id=b.id')
            ->select();
            $exp = $expQuery->where('user_id',$id)->find();

            $myProjects = $projQuery->where('leader_id',$userId)->field('id,cate_id,name')->select();
            foreach($myProjects as $project){
                //查询每个项目今日是否已经邀请该用户
                $project['has_invite_today'] = bar_has_action_today($userId,$id,$project['id'],2);
                $resultMyProjects[] = $project;
            }
            
            $this->assign([
                'status' => $status,
                'user' => $userBase,
                'roleInfoList' => $roleInfo,
                'tags' => $tags,
                'exp' => $exp,
                'myProjects' => $resultMyProjects
            ]);
            return $this->fetch();

        }else{
            $this->error("未指定个人id");
        }
    }
}