<?php
namespace app\user\controller;

use app\common\controller\BaseController;
use think\Request;
use think\Validate;
use app\common\model\User;
use think\View;

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
            'account' => 'require|email',
            'username' => 'require|min:3|max:20',
            'password' => 'require|min:6|max:32',
            'verify_code' => 'require'
        ];

        $isOpenRegister = bar_is_open_register();

        if($isOpenRegister){
            unset($rules['verify_code']);
        }

        $validate = new Validate($rules);
        $validate->message([
            'account.require' => '邮箱不能为空',
            'username.require' => '用户名不能为空',
            'username.min' => '用户名不能小于3个字符',
            'username.max' => '用户名不能大于20个字符',
            'verify_code.require' => '邮箱验证码不能为空',
            'password.require' => '密码不能为空',
            'password.min' => '密码不能小于6个字符',
            'passowrd.max' => '密码不能大于32个字符'
        ]);
        
        $postData = $this->request->post();
        if(!$validate->check($postData)){
            $this->error($validate->getError());
        }
        
        if(!$isOpenRegister){
            $errMsg = bar_check_verify_code($postData['account'], $postData['verify_code']);
            if(!empty($errMsg)){
                $this->error($errMsg);
            }
        }
        
        $userModel = new User();
        $log = $userModel->registerEmail($postData);

        switch($log){
            case 0:
                $this->success('注册成功，欢迎加入项慕吧！', '/user/public/login');
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
                $this->error('您的账户尚未注册', 'register');
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

    /**
     * 发送邮箱/手机(TODO)验证码
     */
    public function send($account='')
    {   
        if($account){
            //TODO Emailcheck
            $code = bar_get_verify_code($account);
            if(!$code){
                $this->error("验证码发送次数过多，请明天再试，或者联系我们解决。");
            }
            $emailTemplate = bar_get_option('email_template_verify_code');
            $message = htmlspecialchars_decode($emailTemplate['template']);
            $view = new View();
            $message = $view->display($message, ['code' => $code]);
            $subject = $emailTemplate['subject'];
            $result = bar_send_email($account,$subject,$message);
            if(!$result['error']){
                bar_verify_code_log($account,$code);
                return 0;
            }else{
                // echo $result['msg'];
                return $result['msg'];
            }

        }else{
            return 1;
        }
    }
}