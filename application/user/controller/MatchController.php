<?php
namespace app\user\controller;

use think\Validate;
use think\Db;
use app\common\controller\UserBaseController;
use think\Request;

class MatchController extends UserBaseController
{
    /**
     * 为当前用户匹配项目
     */
    public function projects()
    {
        $userId = bar_get_user_id();
        $userTagQuery = Db::name("user_tag");
        $finalData = [];
        $projList = [];
        $result = $userTagQuery
            ->alias('a')
            ->field('b.tag_id,b.proj_id')
            ->where('user_id',$userId)
            ->join('__PROJ_TAG__ b','a.tag_id=b.tag_id')
            ->select();
        if($result!=[]){
            foreach($result as $item){
                $data[] = $item['proj_id'];
            }
            $projQuery = Db::name("project");
            $userProjQuery = Db::name("user_proj");
            $sortData = array_count_values($data);
            arsort($sortData);
            $beforeProjIds = array_keys($sortData);
            $projIds = [];
            foreach($beforeProjIds as $projId){
                $hasJoin = $userProjQuery->where('user_id',$userId)->where('proj_id',$projId)->find();
                if(!$hasJoin){
                    $projIds[] = $projId;
                }
            }
            $count = count($projIds);
            
            if($count > 3) $count = 3;
            for($i=0;$i<$count;$i++){
                $proj = $projQuery
                    ->alias('a')
                    ->join('__CATEGORY__ b','a.cate_id=b.id')
                    ->where('a.id',$projIds[$i])
                    ->field('a.*,b.name as cate_name')
                    ->find();
                $projList[] = $proj;
            }
        }
        $this->assign('projList',$projList);

        return $this->fetch();
    }

    /**
     * 为项目匹配推荐人才
     * @param int 项目ID
     */
    public function partners($pid){
        if($pid){
            $userId = bar_get_user_id();
            $projTagQuery = Db::name("proj_tag");
            $result = $projTagQuery
                ->alias('a')
                ->field('b.tag_id,b.user_id')
                ->where('proj_id',$pid)
                ->where('user_id','<>',$userId)
                ->join('__USER_TAG__ b','a.tag_id=b.tag_id')
                ->select();
            $projName = Db::name("project")->where('id',$pid)->value('name');                
            if($result){
                $isNull = 0;
                foreach($result as $item){
                    $data[] = $item['user_id'];
                }
                $userQuery = Db::name("user");
                $userTagQuery = Db::name("user_tag");
                $sortData = array_count_values($data);
                arsort($sortData);
                $userIds = array_keys($sortData);
                $count = count($userIds);
                if($count > 3) $count = 3;
                $userBaseList = $userQuery->where('id','in',$userIds)->select();
                $userList = bar_user_list_splice($userBaseList);
            }else{
                $userList = [];
                $isNull = 1;
            }
            $this->assign([
                "userList"=>$userList,
                "projId"=>$pid,
                "projName"=>$projName,    
                "isNull" => $isNull
            ]);
            return $this->fetch();
        }else{
            $this->error("未受理的请求");
        }
    }

}