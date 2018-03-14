<?php
namespace app\index\controller;

use app\common\controller\BaseController;
use think\Db;
use app\common\model\Project;

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
            ->where('a.status',1)
            ->field('a.id,a.name,a.image,a.cate_id,a.intro,b.name as cate_name')
            ->order('id','desc')
            ->paginate(8);
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
        if(!$id) return $this->error("该项目不存在");
        $userId = bar_get_user_id();
        $projQuery = Db::name("project");
        $projTagQuery = Db::name("proj_tag");
        $roleQuery = Db::name("role");
        $tagQuery = Db::name("tag");
        $userQuery = Db::name("user");
        $userProjQuery = Db::name("user_proj");
        $cateQuery = Db::name("category");

        $isLeader = 0;
        $hasApplyToday = 0;
        $hasJoin = 0;
        $resultPartners = [];

        $projBaseInfo = $projQuery->where('id',$id)->find();
        if($projBaseInfo == []){
            return $this->error("该项目不存在");
        }
        $projBaseInfo['need'] = json_decode($projBaseInfo['need'],true);
        $cateName = $cateQuery->where('id',$projBaseInfo['cate_id'])->value("name");
        $projBaseInfo['cate_name'] = $cateName;

        $finalBaseInfo = $projBaseInfo;
        unset($finalBaseInfo['need']);
        foreach($projBaseInfo['need'] as $role){
            $roleName = $roleQuery->where('id',$role['type'])->value('name');
            $role['role_name'] = $roleName;
            $finalBaseInfo['need'][] = $role;
        }
        $tags = $projTagQuery
                ->alias('a')
                ->field('b.*')
                ->where(['proj_id' => $id])
                ->join('__TAG__ b','a.tag_id=b.id')
                ->select();
        $leader = $userQuery->where('id',$projBaseInfo['leader_id'])->find();
        if($projBaseInfo['leader_id'] == $userId){
            $isLeader = 1;
            $hasJoin = 1;
        }else{
            $userProjFind = $userProjQuery->where('user_id',$userId)->where('proj_id',$id)->find();
            if($userProjFind) $hasJoin = 1;
        }
        if($hasJoin == 1){
            $partners = $userProjQuery
                ->alias('a')
                ->field('b.id,b.username,b.qq,b.email,b.sex,b.nickname')
                ->where('proj_id',$id)
                ->join('__USER__ b','a.user_id=b.id')
                ->select();
            foreach($partners as $partner){
                $partner['is_leader'] = 0;
                if($partner['id'] == $projBaseInfo['leader_id']){
                    $partner['is_leader'] = 1;
                }
                $resultPartners[] = $partner;
            }
        }else{
            $hasApplyToday = bar_has_action_today($userId,$leader['id'],$id,1);
        }

        $this->assign([
            'baseInfo' => $finalBaseInfo,
            'tags' => $tags,
            'isLeader' => $isLeader,
            'has_apply_today' => $hasApplyToday,
            'has_join' => $hasJoin,
            'leader' => $leader,
            'partners' => $resultPartners
        ]);
        
        return $this->fetch("view");
    }

    /**
     * 搜索项目/人才
     * type:1项目/2人才
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
                    ->where('a.status',1)
                    ->where('a.name','like','%'.$getKey.'%')
                    ->where('b.tag_id',$getTag)
                    ->join('__PROJ_TAG__ b','a.id=b.proj_id')
                    ->join('__CATEGORY__ c','a.cate_id=c.id')
                    ->distinct(true)
                    ->order('a.id','desc')
                    ->paginate(8,false,[
                        'query' => request()->param()
                    ]);
            }else{
                $projList = $projQuery
                    ->alias('a')
                    ->field('a.*,c.name as cate_name')
                    ->where('a.name','like','%'.$getKey.'%')
                    ->where('a.status',1)
                    ->join('__CATEGORY__ c','a.cate_id=c.id')
                    ->distinct(true)
                    ->order('a.id','desc')
                    ->paginate(8,false,[
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
            $tagQuery = Db::name("tag");
            $userList = [];
            if($getTag){
                $getTagName = $tagQuery->where('id',$getTag)->value('name');
                $userBaseList = $userQuery
                    ->alias('a')
                    ->field('a.*')
                    ->where('a.nickname','like','%'.$getKey.'%')
                    ->where('a.status',1)
                    ->where('b.tag_id',$getTag)
                    ->join('__USER_TAG__ b','a.id=b.user_id')
                    ->distinct('a.id')
                    ->order(['list_order'=>'desc','last_login_time'=>'desc'])
                    ->paginate(6,false,[
                        'query' => request()->param()
                    ]);
            }else{
                $userBaseList = $userQuery
                    ->alias('a')
                    ->field('a.*')
                    ->where('a.nickname','like','%'.$getKey.'%')
                    ->where('a.status',1)
                    ->distinct(true)
                    ->order(['list_order'=>'desc','last_login_time'=>'desc'])
                    ->paginate(6,false,[
                        'query' => request()->param()
                    ]);
            }
            $userList = bar_user_list_splice($userBaseList);

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
    public function filter_old()
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
            ->where('a.status',1)
            ->field('a.id,a.name,a.cate_id,a.intro,a.image,b.name as cate_name')
            ->distinct('a.id')
            ->paginate(8,false,[
                'query' => request()->param()
                //目的是有参数时也可以通过param参数分页
            ]);
        }else if(!$cid && $rid){
            $projList = $projQuery
            ->alias('a')
            ->where('role_id',$rid)
            ->where('a.status',1)
            ->join('__CATEGORY__ c','a.cate_id=c.id')
            ->join('__PROJ_SKILL__ b','a.id=b.proj_id')
            ->field('a.id,a.name,a.cate_id,a.intro,a.image,b.role_id,c.name as cate_name')
            ->distinct('a.id')
            ->paginate(8,false,[
                'query' => request()->param() 
            ]);
        }else if($cid && $rid){
            $projList = $projQuery
            ->alias('a')
            ->where(function($query)use($cid){
                $query->where('cate_id',$cid)->whereOr('b.parent_id',$cid);
            })
            ->where('role_id',$rid)
            ->where('a.status',1)
            ->join('__CATEGORY__ b','a.cate_id=b.id')
            ->join('__PROJ_SKILL__ c','a.id=c.proj_id')
            ->field('a.id,a.name,a.cate_id,a.intro,a.image,b.name as cate_name,c.role_id')
            ->distinct(true)
            ->paginate(8,false,[
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
     * 项目筛选
     */
    public function filter(){
        $getData = $this->request->get();
        $rid = isset($getData['role'])?$getData['role']:0;
        $cid = isset($getData['cate1'])?$getData['cate1']:0;
        $cateOne = isset($getData['cate1'])?$getData['cate1']:'';
        $projQuery = Db::name("project");
        $cateQuery = Db::name("category");
        $roleQuery = Db::name("role");
        $projRoleQuery = Db::name("proj_role");
        $firstCates = $cateQuery->where("parent_id",0)->select();
        $roles = $roleQuery->select();
        if(isset($getData['cate2']) && $getData['cate2']){
            $cid = $getData['cate2'];
        }
        if(!$cid && !$rid){
            return $this->redirect($this->request->root().'/');
        }else if($cid && !$rid){
            $projIds = $projQuery
                ->alias('a')
                ->where('cate_id',$cid)
                ->whereOr('b.parent_id',$cid)
                ->join('__CATEGORY__ b','a.cate_id=b.id')
                ->distinct('a.id')
                ->column('a.id');
        }else if(!$cid && $rid){
            $projIds = $projRoleQuery
                ->where('role_id',$rid)
                ->distinct('proj_id')
                ->column('proj_id');
        }else if($cid && $rid){
            $projIds = $projQuery
                ->alias('a')
                ->where(function($query)use($cid){
                    $query->where('a.cate_id',$cid)->whereOr('c.parent_id',$cid);
                })
                ->where('b.role_id',$rid)
                ->join('__CATEGORY__ c','a.cate_id=c.id')
                ->join('__PROJ_ROLE__ b','b.proj_id=a.id')
                ->distinct('a.id')
                ->column('a.id');   
        }
        $projList = $projQuery
            ->alias('a')
            ->where('a.id','in',$projIds)
            ->join('__CATEGORY__ b','a.cate_id=b.id')
            ->field('a.id,a.name,a.cate_id,a.intro,a.image,b.name as cate_name')
            ->distinct('a.id')
            ->order('id','desc')
            ->paginate(8);

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
    
}
