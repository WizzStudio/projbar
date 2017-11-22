<?php

namespace app\user\controller;

use think\Validate;
use think\Db;
use app\common\controller\UserBaseController;
use think\Request;
use app\common\model\User;
use app\common\model\UserSkill;
use app\common\model\Tag;

class ProfileController extends UserBaseController
{
    /**
     * 个人信息首页
     */
    public function center()
    {
        $user = bar_get_current_user();
        $userId = $user['id'];
        $userSkillQuery = Db::name("user_skill");
        $userTagQuery = Db::name("user_tag");
        $expQuery = Db::name("exp");
        $roleInfo = [];
        $exp = '';
        $userSkillList = $userSkillQuery->where('user_id', $userId)->select();      
        if(!empty($userSkillList)){
            foreach($userSkillList as $skill){
                $roleIds[] = $skill['role_id'];
            }

            $roleIds = array_unique($roleIds);
            foreach($roleIds as $roleId){
                foreach($userSkillList as $skill){
                    if($skill['role_id'] == $roleId){
                        $roleInfo[$roleId][] = $skill;
                    }
                }
            }
        }
        $tags = $userTagQuery
            ->alias('a')
            ->field('b.*')
            ->where(['user_id' => $userId])
            ->join('__TAG__ b','a.tag_id=b.id')
            ->select();
        $exp = $expQuery->where('user_id',$userId)->find();

        $this->assign('roleInfoList', $roleInfo);
        $this->assign('user', $user);
        $this->assign('tags',$tags);
        $this->assign('exp',$exp);
        return $this->fetch();
    }

    /**
     * 个人基础资料编辑
     */
    public function edit_base()
    {
        $user = bar_get_current_user();
        $this->assign('user',$user);
        return $this->fetch();
    }

    /**
     * 个人角色信息编辑
     */
    public function edit_role()
    {   
        $userId = bar_get_user_id();
        $userSkillQuery = Db::name("user_skill");
        $userTagQuery = Db::name("user_tag");
        $roleInfo = [];
        $userSkillList = $userSkillQuery->where('user_id', $userId)->select();      
        if(!empty($userSkillList)){
            foreach($userSkillList as $skill){
                $roleIds[] = $skill['role_id'];
            }

            $roleIds = array_unique($roleIds);
            foreach($roleIds as $roleId){
                foreach($userSkillList as $skill){
                    if($skill['role_id'] == $roleId){
                        $roleInfo[$roleId]['name'][] = $skill['name'];
                        $roleInfo[$roleId]['level'][] = $skill['level'];
                    }
                }
                $roleInfo[$roleId] = array_combine($roleInfo[$roleId]['name'], $roleInfo[$roleId]['level']);
            }
        }
        $tags = $userTagQuery
            ->alias('a')
            ->field('b.*')
            ->where(['user_id' => $userId])
            ->join('__TAG__ b','a.tag_id=b.id')
            ->select();

        $this->assign('roleInfoList', $roleInfo);
        $this->assign('tags',$tags);
        return $this->fetch();
    }

    /**
     * 编辑个人资料提交修改
     */
    public function editBase()
    {   
        if($this->request->isPost()){
            $rules = [
                'nickname' => 'chsDash|max:32',
                'sex' => 'number|between:0,2',
                'birthday'   => 'dateFormat:Y-m-d|after:-88 year|before:-1 day',
                'signature'   => 'max:128',
            ];
            $validate = new Validate($rules);
            $validate->message([
                'nickname.chsDash' => '昵称只能包含汉字、字母、数字和下划线_及破折号-',
                'nickname.max' => '昵称最大只能为32个字符',
                'sex.number' => '请选择性别',
                'sex.between' => '该性别选项无效',
                'birthday.dateFormat' => '生日格式不正确',
                'birthday.after' => '出生日期也太早了吧？',
                'birthday.before' => '出生日期也太晚了吧？',
                'signature.max' => '个性签名长度不得超过128个字符',
            ]);

            $postData = $this->request->post();
            if(!$validate->check($postData)){
                $this->error($validate->getError());
            }
            $userModel = new User();
            if($userModel->doBaseEdit($postData)){
                $this->success("个人资料修改成功！",'user/profile/center');
            }else{
                $this->error("没有修改新的信息");
            }
        }else{
            $this->error('请求方式错误');
        }
    }

    /**
     * 编辑个人角色信息
     */
    public function editRole()
    {      
        $post = $this->request->post();
        $roleNumber = count($post['role']);
        foreach($post['role'] as $roleId){
            
        }
        for($i=0;$i<$roleNumber;$i++){
            $roleId = $post['role'][$i];
            $data[$roleId][$post['skill1'][$i]] = $post['level1'][$i];
            $data[$roleId][$post['skill2'][$i]] = $post['level2'][$i];
            $data[$roleId][$post['skill3'][$i]] = $post['level3'][$i];
        }
        $tags = $post['tags'];
        $userSkillModel =  new UserSkill();
        $log = $userSkillModel->doUserSkillEdit($data,$tags,$roleNumber);
        switch($log){
            case 0:
                $this->success('角色信息添加成功！','user/profile/center');
                break;
            case 1:
                $this->error('技能信息添加失败！');
                break;
            case 2:
                $this->error('标签信息添加失败！');
                break;
            default:
                $this->error('未受理的请求');
        }
    }

    /**
     * 我参与的项目
     */
    public function projects()
    {
        
    }

    /**
     * 我的通知消息
     */
    public function message()
    {
        
    }

    /**
     * 测试
     */
    public function test()
    {
        return ;
    }

    /**
     * 用户注销
     */
    public function logout()
    {
        session("user",null);
        return redirect($this->request->root()."/");
    }
}