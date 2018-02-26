<?php
namespace app\common\model;

use think\Model;
use think\Db;

class Project extends Model
{
    /**
     * 项目发布数据业务逻辑处理
     */
    public function doRelease($projData)
    {
        $projQuery = Db::name("project");
        $userProjQuery = Db::name("user_proj");
        $projTagQuery = Db::name("proj_tag");
        $projRoleQuery = Db::name("proj_role");

        $userId = bar_get_user_id();
        $cateId = $projData['category'];
        $projImage = bar_get_proj_image($cateId);
        $currentTime = time();

        $need = json_encode($projData['role']);

        $baseInfo = [
            'name' => $projData['name'],
            'cate_id' => $cateId,
            'leader_id' => $userId,
            'image' => $projImage,
            'intro' => $projData['intro'],
            'need' => $need,
            'create_time' => $currentTime
        ];
        $projId = $projQuery->insertGetId($baseInfo);
        foreach($projData['role'] as $singleRole){
            $projRoleQuery->insert(['role_id'=>$singleRole['type'],'proj_id'=>$projId]);
        }
        if(!$projId) return 1; //基础信息添加失败
        
        foreach($projData['tags'] as $tag){
            $projTagResult = $projTagQuery->insert([
                'proj_id' => $projId,
                'tag_id' => $tag
            ]);
            if(!$projTagResult){
                //标签添加失败
                return 2;
            }
        }

        $userProjResult = $userProjQuery->insert(['proj_id'=>$projId,'user_id'=>$userId]);
        if(!$userProjResult){
            return 4;
        }
        return 0;
    }

    public function doUpdate($projData){
        $projQuery = Db::name("project");
        $userProjQuery = Db::name("user_proj");
        $projTagQuery = Db::name("proj_tag");
        $projRoleQuery = Db::name("proj_role");

        $userId = bar_get_user_id();
        $projId = $projData['id'];
        $cateId = $projData['category'];
        $projImage = bar_get_proj_image($cateId);
        $currentTime = time();

        $need = json_encode($projData['role']);

        $baseInfo = [
            'name' => $projData['name'],
            'cate_id' => $cateId,
            'leader_id' => $userId,
            'image' => $projImage,
            'intro' => $projData['intro'],
            'need' => $need,
            'update_time' => $currentTime
        ];

        $projQuery->where('id',$projId)->update($baseInfo);
        $projRoleQuery->where('proj_id',$projId)->delete();
        foreach($projData['role'] as $singleRole){
            $projRoleQuery->insert(['role_id'=>$singleRole['type'],'proj_id'=>$projId]);
        }

        if(isset($projData['tags'])){
            $tagDelete = $projTagQuery->where('proj_id',$projId)->delete();
            foreach($projData['tags'] as $tag){
                $result = $projTagQuery->insert(['proj_id'=>$projId,'tag_id'=>$tag]);
            }
        }

        return 0;
    }

    /**
     * 查询一个项目的所有信息
     */
    public function findOneProj($id)
    {
        $userId = bar_get_user_id();
        $projQuery = Db::name("project");
        $cateQuery = Db::name("category");
        $projTagQuery = Db::name("proj_tag");
        
        $baseInfo = $projQuery
            ->alias('a')
            ->join('__CATEGORY__ b','a.cate_id = b.id')
            ->field('a.*,b.name as cate_name')
            ->where('a.id',$id)
            ->find();
        $isLeader = 0;
    }
}