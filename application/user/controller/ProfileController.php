<?php

namespace app\user\controller;

use think\Validate;
use app\common\controller\BaseController;
use think\Request;
use app\common\model\User;

class ProfileController extends BaseController
{
    /**
     * 个人信息首页
     */
    public function center()
    {
        $user = bar_get_current_user();
        $this->assign('user', $user);
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
}