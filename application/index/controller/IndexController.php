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
        $cateQuery = Db::name("category");
        $roleQuery = Db::name("role");
        $firstCates = $cateQuery->where('parent_id',0)->select();
        $roles = $roleQuery->select();
        $projList = $projQuery
            ->alias('a')
            ->join('__CATEGORY__ b','a.cate_id=b.id')
            ->field('a.id,a.name,a.image,a.cate_id,a.intro,b.name as cate_name')
            ->order('id','desc')
            ->paginate(9);
        $this->assign([
            'firstCates' => $firstCates,
            'roles' => $roles,
            'projList' => $projList
        ]);
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
            $userQuery = Db::name("user");
            $projTagQuery = Db::name("proj_tag");
            $projSkillQuery = Db::name("proj_skill");
            $projBase = $projQuery
                ->alias('a')
                ->join('__CATEGORY__ b','a.cate_id=b.id')
                ->field('a.*,b.name as cate_name')
                ->where('a.id',$id)
                ->find();
            $isLeader = 0;
            if($userId == $projBase['leader_id'])$isLeader = 1;
            $has_join = 0;
            $has_apply_today = 0;
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
                
                $projSkillList = $projSkillQuery
                    ->where('proj_id',$id)
                    ->select();
                if(!empty($projSkillList)){
                    foreach($projSkillList as $skill){
                        $roleIds[] = $skill['role_id'];
                    }
                    $roleIds = array_unique($roleIds);
                    foreach($roleIds as $roleId){
                        $roleName = Db::name("role")->where('id',$roleId)->value('name as role_name');
                        foreach($projSkillList as $skill){
                            if($skill['role_id'] == $roleId){
                                $roleInfo[$roleName][] = $skill;
                            }
                        }
                    }
                    $leader = $userQuery
                    ->where('id',$projBase['leader_id'])
                    ->find();
                    $userProjFind = $userProjQuery->where('user_id',$userId)->where('proj_id',$id)->find();
                    $partners = [];
                    $resultPartners = [];
                    if($userProjFind){
                        $has_join = 1;
                        $partners = $userProjQuery
                        ->alias('a')
                        ->field('b.id,b.username,b.qq,b.email,b.sex,b.nickname,b.realname')
                        ->where('proj_id',$id)
                        ->join('__USER__ b','a.user_id=b.id')
                        ->select();
                        foreach($partners as $partner){
                            $partner['is_leader'] = 0;
                            if($partner['id'] == $projBase['leader_id']){
                                $partner['is_leader'] = 1;
                            }
                            $resultPartners[] = $partner;
                        }
                    }else{
                        $has_apply_today = bar_has_action_today($userId,$leader['id'],$id,1);
                    }



                    $this->assign([
                        'projBase' => $projBase,
                        'tags' => $tags,
                        'roleInfoList' => $roleInfo,
                        'has_apply_today' => $has_apply_today,
                        'has_join' => $has_join,
                        'isLeader' => $isLeader,
                        'leader' => $leader,
                        'partners' => $resultPartners
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
     * 搜索项目/人才
     */
    public function search()
    {
        $getData = $this->request->get();
        $getKey = empty($getData['key']) ? '':$getData['key'];
        $getTag = empty($getData['tag']) ? '':$getData['tag'];
        $type = $getData['type'];
        $getTagName = '';
        if(empty($getKey) && empty($getTag)){
            if($type == 1){
                $this->redirect($this->request->root().'/');
            }else{
                $this->redirect('index/partner/lists');
            }
        }
        if($type == 1){
            $projQuery = Db::name("project");
            $projTagQuery = Db::name("proj_tag");
            $cateQuery = Db::name("category");
            $tagQuery = Db::name("tag");
            $projList = [];
            if($getTag){
                $getTagName = $tagQuery->where('id',$getTag)->value('name');
                $projList = $projQuery
                    ->alias('a')
                    ->field('a.*,c.name as cate_name')
                    ->where('a.name','like','%'.$getKey.'%')
                    ->where('b.tag_id',$getTag)
                    ->join('__PROJ_TAG__ b','a.id=b.proj_id')
                    ->join('__CATEGORY__ c','a.cate_id=c.id')
                    ->distinct(true)
                    ->order('a.id','desc')
                    ->paginate(1,false,[
                        'query' => request()->param()
                    ]);
            }else{
                $projList = $projQuery
                    ->alias('a')
                    ->field('a.*,c.name as cate_name')
                    ->where('a.name','like','%'.$getKey.'%')
                    ->join('__CATEGORY__ c','a.cate_id=c.id')
                    ->distinct(true)
                    ->order('a.id','desc')
                    ->paginate(1,false,[
                        'query' => request()->param()
                    ]);
            }

            $this->assign([
                'type' => $type,
                'key' => $getKey,
                'tagId' => $getTag,
                'tagName' => $getTagName,
                'projList' => $projList
            ]);
            return $this->fetch('index@index/projects');
        }else if($type == 2){
            $userQuery = Db::name("user");
            $userTagQuery = Db::name("user_tag");
            $userSkillQuery = Db::name("user_skill");
            $tagQuery = Db::name("tag");
            $userList = [];
            if($getTag){
                $getTagName = $tagQuery->where('id',$getTag)->value('name');
                $userBaseList = $userQuery
                    ->alias('a')
                    ->field('a.*')
                    ->where('a.username|a.nickname','like','%'.$getKey.'%')
                    ->where('b.tag_id',$getTag)
                    ->join('__USER_TAG__ b','a.id=b.user_id')
                    ->distinct(true)
                    ->order('id','desc')
                    ->paginate(6,false,[
                        'query' => request()->param()
                    ]);
            }else{
                $userBaseList = $userQuery
                    ->alias('a')
                    ->field('a.*')
                    ->where('a.username|a.nickname','like','%'.$getKey.'%')
                    ->distinct(true)
                    ->order('id','desc')
                    ->paginate(6,false,[
                        'query' => request()->param()
                    ]);
            }
            foreach($userBaseList as $user){
                $user['tags'] = [];
                $user['role'] = [];
                $tags = $userTagQuery
                    ->alias('a')
                    ->field('b.name')
                    ->where('user_id',$user['id'])
                    ->join('__TAG__ b','a.tag_id=b.id')
                    ->select();
                // foreach($tags as $tag){
                //     $user['tags'][] = $tag['name'];
                // }
                $user['tags'] = $tags;
                $skills = $userSkillQuery
                    ->where('user_id',$user['id'])
                    ->select();
                foreach($skills as $skill){
                    $roleName = Db::name("role")->where('id',$skill['role_id'])->value('name');
                    $user['role'][$roleName][$skill['name']] = $skill['level'];
                }
                $userList[] = $user;
            }
            $this->assign([
                'type' => $type,
                'key' => $getKey,
                'tagId' => $getTag,
                'tagName' => $getTagName,
                'userBaseList' => $userBaseList,
                'userList' => $userList
            ]);
            return $this->fetch('index@index/partners');
        }
    }

    /**
     * AJAX:获取子分类
     */
    public function getChildType($pid){
        if($pid){
            $cateQuery = Db::name("category");
            $result = $cateQuery->where('parent_id',$pid)->select();
            return json($result);
        }else{
            return -1;
        }
    }

    /**
     * 筛选按钮实现筛选项目
     */
    public function filter()
    {      
        $getData = $this->request->get();
        $rid = isset($getData['role'])?$getData['role']:0;
        $cid = isset($getData['cate1'])?$getData['cate1']:0;
        $cateOne = isset($getData['cate1'])?$getData['cate1']:'';
        $projQuery = Db::name("project");
        $cateQuery = Db::name("category");
        $roleQuery = Db::name("role");
        $firstCates = $cateQuery->where('parent_id',0)->select();
        $roles = $roleQuery->select();
        if(isset($getData['cate2']) && $getData['cate2']){
            $cid = $getData['cate2'];
        }
        if(!$cid && !$rid){
            return $this->redirect($this->request->root().'/');
        }else if($cid && !$rid){
            $projList = $projQuery
            ->alias('a')
            ->where('cate_id',$cid)
            ->whereOr('b.parent_id',$cid)
            ->join('__CATEGORY__ b','a.cate_id=b.id')
            ->field('a.id,a.name,a.cate_id,a.intro,a.image,b.name as cate_name')
            ->distinct('a.id')
            ->paginate(9,false,[
                'query' => request()->param()
            ]);
        }else if(!$cid && $rid){
            $projList = $projQuery
            ->alias('a')
            ->where('role_id',$rid)
            ->join('__CATEGORY__ c','a.cate_id=c.id')
            ->join('__PROJ_SKILL__ b','a.id=b.proj_id')
            ->field('a.id,a.name,a.cate_id,a.intro,a.image,b.role_id,c.name as cate_name')
            ->distinct('a.id')
            ->paginate(9,false,[
                'query' => request()->param()
            ]);
        }else if($cid && $rid){
            $projList = $projQuery
            ->alias('a')
            ->where(function($query)use($cid){
                $query->where('cate_id',$cid)->whereOr('b.parent_id',$cid);
            })
            ->where('role_id',$rid)
            ->join('__CATEGORY__ b','a.cate_id=b.id')
            ->join('__PROJ_SKILL__ c','a.id=c.proj_id')
            ->field('a.id,a.name,a.cate_id,a.intro,a.image,b.name as cate_name,c.role_id')
            ->distinct(true)
            ->paginate(9,false,[
                'query' => request()->param()
            ]);
        }
        $this->assign([
            'cateOne' => $cateOne,
            'firstCates' => $firstCates,
            'roles' => $roles,
            'cid' => $cid,
            'rid' => $rid,
            'projList' => $projList
        ]);
        return $this->fetch();
    }

    /**
     * AJAX获取筛选后的项目
     */
    public function getFilterProj($cid)
    {  
        if($cid){
            $projQuery =Db::name("project");
            $cateQuery = Db::name("category");
            $projList = $projQuery
            ->alias('a')
            ->where('cate_id',$cid)
            ->whereOr('b.parent_id',$cid)
            ->join('__CATEGORY__ b','a.cate_id=b.id')
            ->field('a.id,a.name,a.image,a.cate_id,a.intro,b.name as cate_name')
            ->order('id','desc')
            ->select();
            return json($projList);
        }else{
            $this->redirect($this->request->root().'/');
        }
    }

    /**
     * AJAX联动异步筛选
     */
    public function getFilterResult($cid=0,$rid=0)
    {   
        $getData = $this->request->get();
        print_r($getData);
        return ;
        if($cid || $rid){
            $projQuery = Db::name("project");
            $cateQuery = Db::name("category");
            $projSkillQuery = Db::name("proj_skill");
            if(!$cid && $rid){
                $projList = $projQuery
                    ->alias('a')
                    ->where('role_id',$rid)
                    ->join('__CATEGORY__ c','a.cate_id=c.id')
                    ->join('__PROJ_SKILL__ b','a.id=b.proj_id')
                    ->field('a.id,a.name,a.cate_id,a.intro,a.image,b.role_id,c.name as cate_name')
                    ->distinct('a.id')
                    ->select();
            }else if($cid && !$rid){
                $projList = $projQuery
                    ->alias('a')
                    ->where('cate_id',$cid)
                    ->whereOr('b.parent_id',$cid)
                    ->join('__CATEGORY__ b','a.cate_id=b.id')
                    ->field('a.id,a.name,a.cate_id,a.intro,a.image,b.name as cate_name')
                    ->distinct('a.id')
                    ->select();
            }else if($cid && $rid){
                $projList = $projQuery
                ->alias('a')
                ->where(function($query)use($cid){
                    $query->where('cate_id',$cid)->whereOr('b.parent_id',$cid);
                })
                ->where('role_id',$rid)
                ->join('__CATEGORY__ b','a.cate_id=b.id')
                ->join('__PROJ_SKILL__ c','a.id=c.proj_id')
                ->field('a.id,a.name,a.cate_id,a.intro,a.image,b.name as cate_name,c.role_id')
                ->distinct('a.id')
                ->select();
            }
            return json($projList);
        }else{
            $projQuery = Db::name("project");
            $projList = $projQuery
            ->alias('a')
            ->join('__CATEGORY__ b','a.cate_id=b.id')
            ->field('a.id,a.name,a.image,a.cate_id,a.intro,b.name as cate_name')
            ->order('id','desc')
            ->select();
            return json($projList);
        }
    }
}
