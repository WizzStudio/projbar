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
        $projSkillQuery = Db::name("proj_skill");

        $userId = bar_get_user_id();
        $projImage = bar_get_proj_image();
        $currentTime = time();
        $baseInfo = [
            'name' => $projData['name'],
            'cate_id' => $projData['category'],
            'leader_id' => $userId,
            'image' => $projImage,
            'intro' => $projData['intro'],
            'create_time' => $currentTime
        ];
        $projId = $projQuery->insertGetId($baseInfo);
        if(!$projId) return 1; //基础信息添加失败
        
        foreach($projData['tags'] as $tag){
            $projTagResult = $projTagQuery->insert([
                'proj_id' => $projId,
                'tag_id' => $tag
            ]);
            if(!$projTagResult){
                //标签添加失败
                bar_release_rollback($projId);
                return 2;
            }
        }

        foreach($projData['role'] as $role){
            $type = $role['type'];
            foreach($role['skill'] as $i=>$skill){
                if(!$skill) continue;
                $level = $role['level'][$i];
                $insertSkill = [
                    'proj_id' => $projId,
                    'role_id' => $type,
                    'name' => $skill,
                    'level' => $level
                ];
                $projSkillResult = $projSkillQuery->insert($insertSkill);
                if(!$projSkillResult){
                    // 需求角色信息添加错误
                    bar_release_rollback($projId);
                    return 3;
                }
            }
        }

        $userProjResult = $userProjQuery->insert(['proj_id'=>$projId,'user_id'=>$userId]);
        if(!$userProjResult){
            bar_release_rollback($projId);
            return 4;
        }
        return 0;
    }
}