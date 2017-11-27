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
            $projQuery = Db::name("project");
            $projTagQuery = Db::name("proj_tag");
            $projSkillQuery = Db::name("proj_skill");
            $projBase = $projQuery->where('id',$id)->find();
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

                    $this->assign([
                        'projBase' => $projBase,
                        'tags' => $tags,
                        'roleInfoList' => $roleInfo,
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
}
