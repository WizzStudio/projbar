<?php
namespace app\user\controller;

use app\common\controller\BaseController;
use think\Request;
use think\Validate;
use app\common\model\User;
use think\View;
use think\Db;

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
            'username' => 'require|min:3|max:20|alphaDash',
            'nickname' => 'require|max:20|chsDash',
            'password' => 'require|min:4|max:24',
            'repassword' => 'require|confirm:password',
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
            return json(['status'=>1,'msg'=>$validate->getError(),'data'=>""]);
        }
        
        if(!$isOpenRegister){
            $errMsg = bar_check_verify_code($postData['account'], $postData['verify_code']);
            if(!empty($errMsg)){
                // $this->error($errMsg);
                return json(['status'=>1,'msg'=>$errMsg,'data'=>""]);
            }
        }
        
        $userModel = new User();
        $log = $userModel->registerEmail($postData);

        switch($log){
            case 0:
                // $this->success('注册成功，欢迎加入项慕吧！', '/user/public/login');
                return json(['status'=>0,'msg'=>'注册成功，欢迎加入项慕吧！','data'=>""]);
                break;
            case 1:
                // $this->error('该邮箱已被注册');
                return json(['status'=>1,'msg'=>'该邮箱已被注册','data'=>""]);
                break;
            case 2:
                // $this->error('该用户名已被注册');
                return json(['status'=>1,'msg'=>'该用户名已被注册','data'=>""]);
                break;
            default:
                // $this->error('未受理的请求');
                return json(['status'=>1,'msg'=>'未受理的请求','data'=>""]);
        }

    }

    /**
     * 用户登录页面
     * @param $ref 跳转前的url
     */
    public function login($ref = '')
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
        ]);

        if(!$validate->check($postData)){
            return json([
                "status" => 1,
                "msg" => $validate->getError(),
                "data" => ""
            ]);
        }

        $userModel = new User();
        if(Validate::is($postData['account'], 'email')){
            $log = $userModel->doEmail($postData);
        }else{
            $log = $userModel->doName($postData);
        }

        switch($log){
            case 0:
                return json(['status'=>0,'msg'=>'登录成功，欢迎您！','data'=>""]);
                break;
            case 1:
                return json(['status'=>1,'msg'=>'您的账户尚未注册','data'=>""]);
                break;
            case 2:
                return json(['status'=>1,'msg'=>'登录密码错误','data'=>""]);
                break;
            case 3:
                return json(['status'=>1,'msg'=>'您的账号暂时已被封锁，解封请联系我们','data'=>""]);
                break;
            default:
                return json(['status'=>1,'msg'=>'未受理的请求','data'=>""]);

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
                $this->error("注册或找回密码的验证码发送次数过多，请明天再试，或者联系我们解决。");
            }
            $emailTemplate = bar_get_option('email_template_verify_code');
            $message = htmlspecialchars_decode($emailTemplate['template']);
            $view = new View();
            $message = $view->display($message, ['code' => $code]);
            $subject = $view->display($emailTemplate['subject'],['code' => $code]);
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


    /**
     * AJAX:获取用户创建的项目列表
     */
    public function myprojects(){
        $userId = bar_get_user_id();
        if($userId == 0){
            $url = url('user/public/login');
            return json(['status'=>1,'msg'=>'您还未登录，请登录后再进行操作～','data'=>$url]);
        }
        $projQuery = Db::name("project");
        $myProjects = $projQuery->where("leader_id",$userId)->field('id,cate_id,name')->select();
        if($myProjects ==  []){
            $url = url('user/action/release');
            return json(['status'=>1,'msg'=>'请先发布一个项目再进行邀请～','data'=>$url]);
        }
        return json(['status'=>0,'msg'=>'ok','data'=>$myProjects]);
    }

    

    /**
     * 找回密码页
     */
    public function find_pass()
    {
        return $this->fetch();
    }

    /**
     * 找回密码表单处理
     */
    public function find_pass_handle()
    {
        if($this->request->isPost()){
            $data = $this->request->post();
            $errMsg = bar_check_verify_code($data['email'],$data['verify_code']);
            if(!empty($errMsg)){
                $this->error($errMsg);
            }
            $userModel = new User();
            $resultData = [
                'email' => $data['email'],
                'password' => $data['new_pass']
            ];
            $log = $userModel->resetPass($resultData);
            if($log == 0){
                $this->success('重置密码成功！请重新登录','user/public/login');
            }else if($log == 1){
                $this->error('新密码不能和当前密码相同！');
            }else{
                $this->error('内部错误，请重试');
            }
        }else{
            $this->error('请求方式错误！');
        }
    }

    /**
     * 验证是否已登录
     */
    public function has_login()
    {
        $userId = bar_get_user_id();
        if($userId){
            return 1;
        }else{
            return 0;
        }
    }

    /**
     * 判定是否可以给予发布项目权利
     */
    public function release_auth()
    {
        $userId = bar_get_user_id();
        if($userId){
            if(!bar_get_release_auth($userId)){
                return json(['status'=>1,'msg'=>'您今天的发布项目次数已经达到上限，请明天再来']);
            }
            return json(['status'=>0,'msg'=>'ok']);
        }else{
            return json(['status'=>2,'msg'=>'您还未登录，请登录后再进行“项目发布”操作']);
        }
    }

    /**
     * AJAX:是否能够申请加入项目
     */
    public function can_apply()
    {
        $userId = bar_get_user_id();
        if($userId){
            $infoNum = Db::name("user")->where('id',$userId)->value('list_order');
            if($infoNum >= 2){
                return json(['status'=>0,'msg'=>'','data'=>'']);
            }else{
                $url = url("user/profile/edit_role");
                return json(['status'=>1,'msg'=>'请完善您的技能信息后再申请项目～','data'=>$url]);
            }
        }else{
            $url = url("user/public/login");
            return json(['status'=>1,'msg'=>'您还未登录，请登录后再进行“申请加入”操作','data'=>$url]);
        }
    }

}