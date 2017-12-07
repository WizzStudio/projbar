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
        // print_r($getData);
        // return ;
        $key = empty($getData['key']) ? '':$getData['key'];
        $tag = empty($getData['tag']) ? '':$getData['tag'];
        $type = $getData['type'];
        if(empty($key) && empty($tag)){
            if($type == 1){
                $this->redirect($this->request->root().'/');
            }else{
                $this->redirect('index/partner/lists');
            }
        }
        if($type == 1){
            $item = "project";
            $itemTag = "proj_tag";
            $itemUp = '__PROJ_TAG__ b';
            $itemId = 'proj_id';
            $itemName = 'name';
        }elseif($type == 2){
            $item = "user";
            $itemTag = "user_tag";
            $itemUp = '__USER_TAG__ b';
            $itemId = 'user_id';
            $itemName = 'nickname';
        }
        $itemQuery = Db::name($item);
        $itemTagQuery = Db::name($itemTag);
        $itemList = [];
        if($tag){
            $itemList = $itemQuery
            ->alias('a')
            ->field('a.*')
            ->where('a.'.$itemName,'like','%'.$key.'%')
            ->where('b.tag_id',$tag)
            ->join($itemUp,'a.id=b.'.$itemId)
            ->distinct(true)
            ->select();
        }else{
            $itemList = $itemQuery->where($itemName,'like','%'.$key.'%')->distinct(true)->select();
        }
        if($type == 1){
            $this->assign([
                'type' => $type,
                'key' => $key,
                'tag' => $tag,
                'itemList' => $itemList
            ]);
            return $this->fetch('index@index/projects'); 
        }elseif($type == 2){
            $userSkillQuery = Db::name("user_skill");
            $userList = [];
            foreach($itemList as $user){
                if(!$user['nickname']){
                    $user['nickname'] = $user['username'];
                }
                $user['tags'] = [];
                $user['role'] = [];
                $tagSelect = $itemTagQuery
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
            $this->assign([
                'type' => $type,
                'key' => $key,
                'tag' => $tag,
                'userList' => $userList
            ]);
            return $this->fetch('index@index/partners');
        }
    }
}
