<?php
namespace app\index\controller;

use think\Validate;
use think\Db;
use app\common\controller\BaseController;

class PartnerController extends BaseController
{
    /**
     * 个人列表
     */
    public function list()
    {
        $userQuery = Db::name("user");
        $userTagQuery = Db::name("user_tag");
        $userSkillQuery = Db::name("user_skill");
        $userBase = $userQuery->field('id,username,sex,nickname')->select();
        $userList = [];
        foreach($userBase as $user){
            if(!$user['nickname']){
                $user['nickname'] = $user['username'];
            }
            $user['tags'] = [];
            $user['role'] = [];
            $tagSelect = $userTagQuery
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
        // print_r($userList);
        // return ;
        $this->assign("userList",$userList);
        return $this->fetch();
    }
}