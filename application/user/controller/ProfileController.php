<?php

namespace app\user\controller;

use think\Validate;
use think\Db;
use app\common\controller\UserBaseController;
use think\Request;
use app\common\model\User;
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
        $userQuery = Db::name("user");
        $userTagQuery = Db::name("user_tag");
        $msgQuery = Db::name("message");
        $roleQuery = Db::name("role");
        $userProjQuery = Db::name("user_proj");
        $projQuery = Db::name("project");
        $roleInfo = [];
        $myProj = [];
        $msgs = [];
        
        $baseInfo = $userQuery->where("id",$userId)->find();
        $roleInfo = json_decode($baseInfo['role'],true);
        $roleInfo['role_name'] = $roleQuery->where('id',$roleInfo['type'])->value('name');

        $tags = $userTagQuery
            ->alias('a')
            ->field('b.*')
            ->where(['user_id' => $userId])
            ->join('__TAG__ b','a.tag_id=b.id')
            ->select();

        $msgs = $msgQuery
            ->alias('a')
            ->field('a.*,b.cate_id,b.name,c.nickname')
            ->where('to_id',$userId)
            ->where('has_handle', 0)
            ->join('__PROJECT__ b','a.proj_id=b.id')
            ->join('__USER__ c','a.from_id=c.id')
            ->order('id desc')
            ->select();
        $sysMsgs = [];
        $sysMsgs = $msgQuery->where('to_id',$userId)->where('has_handle',0)->select();

        $myProjList = $userProjQuery
            ->alias('a')
            ->field('b.id,b.cate_id,b.name,b.intro,c.name as cate_name')
            ->where('user_id',$userId)
            ->join('__PROJECT__ b','a.proj_id=b.id')
            ->join('__CATEGORY__ c','b.cate_id=c.id')
            ->select();

        $this->assign([
            'roleInfo' => $roleInfo,
            'baseInfo' => $baseInfo,
            'tags' => $tags,
            'msgs' => $msgs,
            'sysMsgs' => $sysMsgs,
            'myProjList' => $myProjList
        ]);
        
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
        $userQuery = Db::name("user");
        $userTagQuery = Db::name("user_tag");
        $roleQuery = Db::name("role");
        $tagQuery = Db::name("tag");
        $allRole = $roleQuery->select();
        $allTag = $tagQuery->select();
        $baseInfo = $userQuery->where('id',$userId)->field('role,exp')->find();
        $roleInfo = $baseInfo['role'];
        $roleInfo = json_decode($roleInfo,true);
        $roleName = $roleQuery->where('id',$roleInfo['type'])->value('name');
        $roleInfo['role_name'] = $roleName;
        $exp = $baseInfo['exp'];
        $userTagId = $userTagQuery->where('user_id',$userId)->column('tag_id');

        $this->assign([
            'allRole' => $allRole,
            'allTag' => $allTag,
            'roleInfo' => $roleInfo,
            'exp' => $exp,
            'userTag' => $userTagId
        ]);
        return $this->fetch();

        return $this->fetch();
    }

    /**
     * 编辑个人资料提交修改
     */
    public function edit_base_handle()
    {   
        if($this->request->isPost()){
            $rules = [
                'nickname' => 'chsDash|max:20',
                'sex' => 'number|between:0,2',
                'mobile' => 'number|max:18',
                'qq' => 'number|max:15',
            ];
            $validate = new Validate($rules);
            $validate->message([
                'nickname.chsDash' => '昵称只能包含汉字、字母、数字和下划线_及破折号-',
                'nickname.max' => '昵称最大只能为20个字符',
                'sex.number' => '请选择性别',
                'sex.between' => '该性别选项无效',
            ]);

            $postData = $this->request->post();
            if(!$validate->check($postData)){
                $errMsg = $validate->getError();
                return json(['status'=>1,'msg'=>$errMsg]);
            }
            $userModel = new User();
            if($userModel->doBaseEdit($postData)){
                return json(['status'=>0,'msg'=>'个人资料修改成功！']);
            }else{
                // $this->error("没有修改新的信息");
                return json(['status'=>1,'msg'=>'没有任何信息的更新']);
            }
        }else{
            $this->error('请求方式错误');
        }
    }

    /**
     * 编辑个人角色信息
     */
    public function edit_role_handle()
    {
        if(!$this->request->isPost()){
            $this->error("请求方式错误");
        }
        $post = $this->request->post();
        $succ = ['status'=>0,'msg'=>'ok'];
        $err = ['status'=>1,'msg'=>'failed'];
        if(!$post['role']['type']){
            $err['msg'] = '请选择您能够担任的角色';
            return json($err);
        }
        if(!$post['role']['skill'][0]){
            $err['msg'] = '请填写至少一个技能';
            return json($err);
        }
        if(isset($post['tags'])){
            $tagNum = count($post['tags']);
            if($tagNum > 6){
                $err['msg'] = '标签不能超过6个！';
                return json($err);
            }   
        }
        //TODO 对于简介信息的关键字和联系方式过滤

        $userModel = new User();
        $log = $userModel->doRoleEdit($post);
        if($log == 0){
            $succ['msg'] = '更新个人技能信息成功，您现在可以申请加入项目啦～';
            return json($succ);
        }else{
            $err['msg'] = '数据库错误';
            return json($err);
        }
    }

    /**
     * 修改密码
     */
    public function pass_update()
    {
        return $this->fetch();
    }

    /**
     * 修改密码处理
     */
    public function pass_update_handle()
    {   
        $post = $this->request->post();
        $validate = new Validate([
            'old_pass' => 'require|min:4|max:24',
            'new_pass' => 'require|min:4|max:24',
            're_new_pass' => 'require|confirm:new_pass',
            'captcha' => 'require|captcha'
        ]);
        if(!$validate->check($post)){
            $this->error($validate->getError());
        }
        if($post['old_pass'] == $post['new_pass']){
            $this->error('新密码不能与当前密码相同');
        }
        $userModel = new User();
        $log = $userModel->changePass($post['old_pass'],$post['new_pass']);
        if($log == 0){
            $this->success('修改密码成功！','/');
        }else if($log == 1){
            $this->error('输入的当前密码不正确');
        }else{
            $this->error('内部错误，如果一直遇到此问题请联系我们');            
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
     * 用户注销
     */
    public function logout()
    {
        session("user",null);
        return redirect($this->request->root()."/");
    }
}