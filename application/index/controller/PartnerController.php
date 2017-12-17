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
                ->alias('a')
                ->join('__ROLE__ b','a.role_id=b.id')
                ->where(['user_id' => $user['id']])
                ->field('a.*,b.name as role_name')
                ->select();
            foreach($skillSelect as $skill){
                $user['role'][$skill['role_name']][$skill['name']] = $skill['level'];
            }
            $num = count($user['role']);
            if($num > 1) array_pop($user['role']);
            $userList[] = $user;
        }
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
                    $roleName = Db::name("role")->where('id',$roleId)->value('name');                    
                    foreach($userSkillList as $skill){
                        if($skill['role_id'] == $roleId){
                            $roleInfo[$roleName][] = $skill;
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

    /**
     * 获得筛选后的人才列表(有问题！！！！！)
     */
    public function filter()
    {
        $getData = $this->request->get();
        $page = isset($getData['page'])?$getData['page']:1;
        $rid = isset($getData['role'])?$getData['role']:0;        
        $tid = isset($getData['tag'])?$getData['tag']:0;
        $userQuery = Db::name("user");
        $userTagQuery = Db::name("user_tag");
        $tagQuery = Db::name("tag");
        $userSkillQuery = Db::name("user_skill");
        $roleQuery = Db::name("role");
        $tags = $tagQuery->select();
        $roles = $roleQuery->select();
        
        if(!$rid && !$tid){
            return $this->redirect(url('index/partner/lists'));
        }else{
            $userBaseList = $userQuery
                ->field('id,username,nickname,sex')
                ->order('id','desc')
                // ->page($page,3)
                ->select();

            $roleInfoList = [];
            $exp = '';
            $userList = [];

            foreach($userBaseList as $user){
                $user['tags'] = [];
                $user['role'] = [];
                if($rid && !$tid){
                    $skills = $userSkillQuery
                        ->where('user_id',$user['id'])
                        ->where('role_id',$rid)
                        ->select();
                    if(!$skills) continue;
                    foreach($skills as $skill){
                        $roleName = Db::name("role")->where('id',$skill['role_id'])->value('name');
                        $user['role'][$roleName][$skill['name']] = $skill['level'];
                    }
                    $num = count($user['role']);
                    if($num > 1) array_pop($user['role']);
                    $tags = $userTagQuery
                    ->alias('a')
                    ->field('b.id,b.name')
                    ->where('user_id',$user['id'])
                    ->join('__TAG__ b','a.tag_id=b.id')
                    ->select();
                    foreach($tags as $tag){
                        $user['tags'][] = $tag['name'];
                    }
                }else if(!$rid && $tid){
                    $tags = $userTagQuery
                    ->alias('a')
                    ->field('b.id,b.name')
                    ->where('user_id',$user['id'])
                    ->where('tag_id',$tid)
                    ->join('__TAG__ b','a.tag_id=b.id')
                    ->select();
                    if(!$tags)continue;
                    foreach($tags as $tag){
                        $user['tags'][] = $tag['name'];
                    }
                    $skills = $userSkillQuery
                        ->where('user_id',$user['id'])
                        ->select();
                    foreach($skills as $skill){
                        $user['role'][$skill['role_id']][$skill['name']] = $skill['level'];
                    }
                    $num = count($user['role']);
                    if($num > 1) array_pop($user['role']);
                }else if($rid && $tid){
                    $tags = $userTagQuery
                    ->alias('a')
                    ->field('b.id,b.name')
                    ->where('user_id',$user['id'])
                    ->where('tag_id',$tid)
                    ->join('__TAG__ b','a.tag_id=b.id')
                    ->select();
                    if(!$tags)continue;
                    $skills = $userSkillQuery
                    ->where('user_id',$user['id'])
                    ->where('role_id',$rid)
                    ->select();
                    if(!$skills) continue;
                    foreach($tags as $tag){
                        $user['tags'][] = $tag['name'];
                    }
                    foreach($skills as $skill){
                        $user['role'][$skill['role_id']][$skill['name']] = $skill['level'];
                    }
                }
                $userList[] = $user;
            }
        }
        $this->assign([
            'userBaseList' => $userBaseList,
            "userList" => $userList,
            "roles" => $roles,
            "tags" => $tags
        ]);
        return $this->fetch();
    }
}