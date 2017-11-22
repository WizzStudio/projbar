<?php
namespace app\user\controller;

use app\common\controller\BaseController;
use think\Request;
use think\Validate;
use app\common\model\User;

class PublicController extends BaseController
{
    /**
     * 用户注册
     */
    public function register()
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
    public function doRegister(Request $request)
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

    /**
     * 用户登录页面
     */
    public function login()
    {   
        if(bar_is_user_login()){
            $this->success('您已登录:)','user/profile/center');
            // return redirect($this->request->root());
        }else{
            return $this->fetch();
        }
    }

    /**
     * 用户登录处理
     */
    public function dologin()
    {
        if(!$this->request->isPost()){
            $this->error('请求方式错误','user/public/login');
        }
        $postData = $this->request->post();
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
                $this->success('登录成功，欢迎您！',$this->request->root()."/");
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