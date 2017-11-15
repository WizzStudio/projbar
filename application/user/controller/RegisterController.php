<?php

namespace app\user\controller;

use app\common\controller\BaseController;
use app\common\model\User;
use think\Request;
use think\View;
use think\Validate;

class RegisterController extends BaseController
{
    /**
     * 用户注册
     */
    public function index()
    {   
        if(bar_is_user_login()){
            return redirect($this->request->root().'/');
        }else{
            return $this->fetch();
        }
    }

    /**
     * 用户注册表单提交
     */
    public function doRegister()
    {
        if(!$this->request->isPost()){
            $this->error('请求方式错误！');
        }

        $rules = [
            'email' => 'require|email',
            'username' => 'require|min:3|max:20',
            'password' => 'require|min:6|max:32',
            'captcha' => 'require|captcha',
            'verify_code' => 'require'
        ];

        $isOpenRegister = bar_is_open_register();

        if($isOpenRegister){
            unset($rules['verify_code']);
        }

        $validate = new Validate($rules);
        $validate->message([
            'email.require' => '邮箱不能为空',
            'username.require' => '用户名不能为空',
            'username.min' => '用户名不能小于3个字符',
            'username.max' => '用户名不能大于20个字符',
            'verify_code.require' => '邮箱验证码不能为空',
            'captcha.require' => '图像验证码不能为空',
            'captcha.captcha' => '图像验证码不正确',
            'password.require' => '密码不能为空',
            'password.min' => '密码不能小于6个字符',
            'passowrd.max' => '密码不能大于32个字符'
        ]);
        
        $postData = $this->request->post();
        if(!$validate->check($postData)){
            $this->error($validate->getError());
        }
        
        $userModel = new User();

        $log = $userModel->registerEmail($postData);

        switch($log){
            case 0:
                $this->success('注册成功，欢迎加入项慕吧！', '/user/login/index');
                break;
            case 1:
                $this->error('该邮箱已被注册');
                break;
            case 2:
                $this->error('该用户名已被注册');
                break;
            default:
                $this->error('未受理的请求');
        }

    }
}