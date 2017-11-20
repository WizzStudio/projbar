<?php
namespace app\common\model;

use think\Db;
use think\Model;

class UserSkill extends Model
{
    /**
     * 添加用户的角色和技能
     */
    public function doUserSkillEdit($data,$tags,$roleNumber)
    {
        $userSkillQuery = Db::name("user_skill");
        $userTagQuery = Db::name("user_tag");
        $userId = bar_get_user_id();
        foreach($data as $roleId => $skillData)
        {
            foreach($skillData as $skillName => $skillLevel){
                $insertSkill = [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'name' => $skillName,
                    'level' => $skillLevel
                ];
                $skillResult = $userSkillQuery->insert($insertSkill);
                if(!$skillResult){
                    return 1;
                }
            }
        }
        foreach($tags as $tag){
            $tagResult = $userTagQuery->insert([
                'user_id' => $userId,
                'tag_id' => $tag
            ]);
            if(!$tagResult){
                return 2;
            }
        }
        return 0;
    }
}