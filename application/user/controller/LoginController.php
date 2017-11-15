<?php

namespace app\user\controller;

use app\common\controller\BaseController;
use think\Request;
use think\Validate;
use app\common\model\User;

class LoginController extends BaseController
{
    /**
     * 用户登录页面
     */
    public function index()
    {   
        if(bar_is_user_login()){
            // return redirect($this->request->root().'/');
            return $this->fetch();
        }else{
            return $this->fetch();
        }
    }

    /**
     * 用户登录处理
     */
    public function doLogin()
    {
        if(!$this->request->isPost()){
            $this->error('请求方式错误');
        }

        $rules = [
            'account' => 'require',
            'password' => 'require|min:6|max:32',
            'captcha' => 'require|captcha'
        ];

        $validate = new Validate($rules);

        $validate->message([
            'account.require' => '用户名/邮箱不能为空',
            'password.require' => '密码不能为空',
            'password.min' => '密码不能小于6个字符',
            'password.max' => '密码不能大于32个字符',
            'captcha.require' => '验证码不能为空',
            'captcha.captcha' => '验证码不正确'
        ]);

        $postData = $this->request->post();
        if(!$validate->check($postData)){
            $this->error($validate->getError());
        }

        $userModel = new User();

        if(Validate::is($postData['account'], 'email')){
            $log = $userModel->doEmail($postData);
        }else{
            $log = $userModel->doName($postData);
        }

        switch($log){
            case 0:
                $this->success('登录成功，欢迎您！','/');
                break;
            case 1:
                $this->error('您的账户尚未注册', 'user/register/index');
                break;
            case 2:
                $this->error('登录密码错误');
                break;
            case 3:
                $this->error('您的账号暂时已被封锁，解封请联系我们');
                break;
            default:
                $this->error('未受理的请求');
        }
    }

}