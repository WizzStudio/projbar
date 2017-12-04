<?php
namespace app\common\model;

use think\Model;
use think\Db;

class Project extends Model
{
    /**
     * 项目发布数据业务逻辑处理
     */
    public function doRelease($data,$tags,$baseInfo,$roleNumber)
    {
        $projectQuery = Db::name("project");
        $userProjQuery = Db::name("user_proj");
        $projTagQuery = Db::name("proj_tag");
        $projSkillQuery = Db::name("proj_skill");
        $userId = bar_get_user_id();

        $projId = Db::name("project")->insertGetId($baseInfo);
        if($projId){
            foreach($tags as $tag){
                $projTagResult = $projTagQuery->insert([
                    'proj_id' => $projId,
                    'tag_id' => $tag
                ]);
                if(!$projTagResult){
                    //项目某个标签添加失败
                    return 2;
                }
            }

            foreach($data as $roleId => $skillData)
            {
                foreach($skillData as $skillName => $skillLevel)
                {
                    $insertSkill = [
                        'proj_id' => $projId,
                        'role_id' => $roleId,
                        'name' => $skillName,
                        'level' => $skillLevel
                    ];
                    $projSkillResult = $projSkillQuery->insert($insertSkill);
                    if(!$projSkillResult){
                        //项目需求角色信息添加出错
                        return 3;
                    }
                }
            }
            $userProjResult = $userProjQuery->insert(['proj_id'=>$projId,'user_id'=>$userId]);
            if($userProjResult){
                //success
                return 0;
            }else{
                return 4;
            }
        }else{
            //基本信息添加失败
            return 1;
        }
    }
}