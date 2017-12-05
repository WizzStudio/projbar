<?php
namespace app\index\controller;

use app\common\controller\BaseController;
use think\Db;

class IndexController extends BaseController
{   
    /**
     * 首页+项目列表
     */
    public function index()
    {   
        $projQuery = Db::name("project");
        $projList = $projQuery->select();
        $this->assign('projList',$projList);
        return $this->fetch();
    }

    /**
     * 查看项目详情
     */
    public function view($id='')
    {
        if($id){
            $userId = bar_get_user_id();
            $projQuery = Db::name("project");
            $userProjQuery = Db::name("user_proj");
            $projTagQuery = Db::name("proj_tag");
            $projSkillQuery = Db::name("proj_skill");
            $projBase = $projQuery->where('id',$id)->find();
            $has_join = 0;
            if($userId == $projBase['leader_id']){
                $has_join = 1;
            }
            if($projBase){
                $tags = $projTagQuery
                ->alias('a')
                ->field('b.*')
                ->where(['proj_id' => $id])
                ->join('__TAG__ b','a.tag_id=b.id')
                ->select();
                
                $projSkillList = $projSkillQuery->where('proj_id',$id)->select();
                if(!empty($projSkillList)){
                    foreach($projSkillList as $skill){
                        $roleIds[] = $skill['role_id'];
                    }
                    $roleIds = array_unique($roleIds);
                    foreach($roleIds as $roleId){
                        foreach($projSkillList as $skill){
                            if($skill['role_id'] == $roleId){
                                $roleInfo[$roleId][] = $skill;
                            }
                        }
                    }
                    $leader = $projQuery
                        ->alias('a')
                        ->field('b.id,b.username,b.sex,b.qq,b.nickname,b.realname')
                        ->where('a.id',$id)
                        ->join('__USER__ b','a.leader_id=b.id')
                        ->find();
                    $userProjFind = $userProjQuery->where('user_id',$userId)->where('proj_id',$id)->find();
                    $partners = [];
                    if($userProjFind){
                        $has_join = 1;
                        $partners = $userProjQuery
                        ->alias('a')
                        ->field('b.id,b.username,b.qq,b.email,b.sex,b.nickname,b.realname')
                        ->where('proj_id',$id)
                        ->join('__USER__ b','a.user_id=b.id')
                        ->select();
                    }

                    $this->assign([
                        'projBase' => $projBase,
                        'tags' => $tags,
                        'roleInfoList' => $roleInfo,
                        'has_join' => $has_join,
                        'leader' => $leader,
                        'partners' => $partners
                    ]);
                    return $this->fetch();
                }else{
                    $this->error('项目角色信息查询失败');
                }
            }else{
                $this->error('该项目不存在，请不要搞事:)');
            }
        }else{
            $this->error('未指定项目id');
        }
    }

    /**
     * 搜索项目
     */
    public function search()
    {   
        $getData = $this->request->get();
        $key = empty($getData['key']) ? '':$getData['key'];
        $tagIds = isset($getData['tags']) ? $getData['tags'] : [];
        if(empty($key) && empty($tagIds)){
            $this->redirect($this->request->root().'/');
        }
        $projQuery = Db::name("project");
        $projTagQuery = Db::name("proj_tag");
        $result = $projQuery
            ->alias('a')
            ->field('a.*')
            ->where('a.name','like','%'.$key.'%')
            ->where('b.tag_id',$tagIds[0])
            ->join('__PROJ_TAG__ b','a.id=b.proj_id')
            ->distinct(true)
            ->select();
        return json($result);
    }
}
