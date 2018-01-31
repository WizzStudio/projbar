<?php
namespace app\user\controller;

use think\Validate;
use think\Db;
use think\Request;
use app\common\model\Project;
use app\common\controller\UserBaseController;

class ActionController extends UserBaseController
{   
    /**
     * 测试发布
     */
    public function test_del($id='')
    {
       $log = bar_release_rollback($id);
       return $log;
    }

    /**
     * 发布项目
     */
    public function release()
    {   
        $cateQuery = Db::name("category");
        $tagQuery = Db::name("tag");
        $roleQuery = Db::name("role");
        $parentCates = $cateQuery->where('parent_id',0)->select();
        $tags = $tagQuery->select();
        $roles = $roleQuery->select();
        $userId = bar_get_user_id();
        if(!bar_get_release_auth($userId)){
            return json(['status'=>1,'msg'=>'您今天的发布项目次数已经达到上限，请明天再来','data'=>'']);
        }   
        foreach($parentCates as $cate){
            $childCates = $cateQuery->where('parent_id',$cate['id'])->field('id,parent_id,name')->select();
            $cateList[$cate['name']] = $childCates;
        }
        $this->assign([
            'cateList' => $cateList,
            'tags' => $tags,
            'roles' => $roles
        ]);
        return $this->fetch();
    }

    /**
     * 发布项目处理
     */
    public function release_handle(Request $request)
    {   
        $validate = new Validate([
            'name' => 'require|min:2|max:25',
            'category' => 'require',
            'intro' => 'require|max:160',

        ]);

        $success = ["status"=>0,'msg'=>'成功！','data'=>''];
        $error = ["status"=>1,'msg'=>'错误！','data'=>''];

        $post = $request->post();
        if(!$validate->check($post)){
            $error = ['status'=>1,'msg'=>$validate->getError(),'data'=>''];
            return json($error);
        }  
        $tags = isset($post['tags'])?$post['tags']:[];
        $tagNum = count($tags);
        if($tagNum > 6){
            $error['msg'] = '标签不能超过六个';
            return json($error);
        }

        $projectModel = new Project();
        $log = $projectModel->doRelease($post);
        switch($log){
            case 0:
                $success['msg'] = '项目发布成功，当收到“加入项目申请”时，项慕吧会发邮件通知您，请注意查收。';
                return json($success);
            case 1:
                $error['msg'] = '项目基本信息添加失败！';
                return json($error);
            case 2:
                $error['msg'] = '项目标签添加失败！';
                return json($error);
                break;
            case 3:
                $error['msg'] = '项目需求角色信息添加失败！';
                return json($error);
            case 4:
                $error['msg'] = '添加发起人到成员列表失败！';
                return json($error);
            default:
                $error['msg'] = '未受理的请求';
                return json($error);
        }
    }

    /**
     * 用户申请加入项目
     * @return 0成功/1申请消息入库失败/
     */
    public function apply($id='')
    {   
        if($id){
            $userId = bar_get_user_id();
            if(!$userId) return 9;
            $name = empty(session('user.nickname')) ? session('user.username') : session('user.nickname'); 
            $userQuery = Db::name("user");
            $msgQuery = Db::name("message");
            $projQuery = Db::name("project");
            $infoNum = $userQuery->where('id',$userId)->value('list_order');
            if($infoNum < 2){
                //信息不够完善
                return 4;
            }
            $projBase = $projQuery->where('id',$id)->find();
            $has_apply_today = bar_has_action_today($userId,$projBase['leader_id'],$projBase['id'],1);
            if($has_apply_today){
                return 8;
            }
            if($projBase){
                $leaderId = $projBase['leader_id'];
                $toEmail = Db::name("user")->where('id',$leaderId)->value('email');
                $currentTime = time();
                $msgResult = $msgQuery->insert([
                    'from_id' => $userId,
                    'to_id' => $leaderId,
                    'proj_id' => $id,
                    'type' => 1,
                    'send_time' => $currentTime
                ]);
                if($msgResult){
                    $subject = "【项慕吧】通知消息";
                    $message = bar_get_action_email($userId,$name,$id,1);
                    $token = bar_get_user_token($leaderId);
                    if(!$token) return 1;
                    $emailResult = bar_send_email($toEmail,$subject,$message,$token);
                    if(!$emailResult['error']){
                        $logResult = bar_action_email_log($userId,1);//主动动作为1
                        return 0;
                    }else{
                        // $this->error("内部错误：".$emailResult['msg'].",如果一直出现此问题，请反馈给我们");
                        return 2;
                    }
                }else{
                    return 3;
                }
            }
        }
    }

    /**
     * 邀请个人加入项目
     */
    public function invite($uid,$pid)
    {
        if($uid && $pid){
            $userId = bar_get_user_id();
            $name = empty(session('user.nickname')) ? session('user.username') : session('user.nickname'); 
            
            if($userId == $uid){
                // $this->error("不能邀请自己！");    
                return 1;
            }
            $msgQuery = Db::name("message");
            $has_invite_today = bar_has_action_today($userId,$uid,$pid,2);
            if($has_invite_today){
                //今日已经邀请过
                return 4;
            }
            $userProjQuery = Db::name("user_proj");
            $find = $userProjQuery->where(['proj_id'=> $pid,'user_id'=>$uid])->find();
            if($find){
                // $this->error("不能邀请已经在项目内的成员！");
                return 2;
            }
            $currentTime = time();
            $msgResult = $msgQuery->insert([
                'from_id' => $userId,
                'to_id' => $uid,
                'proj_id' => $pid,
                'type' => 2,
                'send_time' => $currentTime
            ]);
            if(!$msgResult){
                // $this->error("发起邀请失败，原因：无法向数据库中插入邀请数据");
                return 3;
            }
            $toEmail = Db::name("user")->where('id',$uid)->value('email');
            $subject = "【项慕吧】通知消息";
            $message = bar_get_action_email($userId,$name,$pid,2);
            $token = bar_get_user_token($uid);
            if(!$token) return 1;
            $emailResult = bar_send_email($toEmail,$subject,$message,$token);
            if(!$emailResult['error']){
                $logResult = bar_action_email_log($userId,1); //主动动作为1
                // $this->success("发送邀请消息成功！");
                return 0;
            }else{
                // $this->error("内部错误：".$emailResult['msg'].",如果一直出现此问题，请反馈给我们");
                return 5;
            }
        }else{
            $this->error("请求参数错误");
        }
    }

    /**
     * 接受邀请或申请
     */
    public function accept($id=''){
        if($id){
            $userId = bar_get_user_id();
            $name = empty(session('user.nickname')) ? session('user.username') : session('user.nickname'); 
            
            $msgQuery = Db::name("message");
            $userProjQuery = Db::name("user_proj");
            $msg = $msgQuery->where('id',$id)->find();
            
            if(!$msg){
                $this->error("不存在该条申请/邀请信息");
            }
            $projId = $msg['proj_id'];
            $returnUserId = $msg['from_id'];
            if($msg['type'] == 1){
                $addUserId = $msg['from_id'];
            }elseif($msg['type'] == 2){
                $addUserId = $msg['to_id'];
            }
            if($msg['to_id'] == $userId){
                $msgUpdateResult = $msgQuery->where('id',$id)->update(['has_handle' => 1]);
                if($msgUpdateResult){
                    $currentTime = time();
                    $msgInsertResult = $msgQuery->insert([
                        'from_id' => $userId,
                        'to_id' => $returnUserId,
                        'proj_id' => $projId,
                        'type' => 3,
                        'send_time' => $currentTime
                    ]);
                    if($msgInsertResult){
                        $currentTime = time();
                        $userProjResult = $userProjQuery->insert(['proj_id'=>$projId,'user_id'=>$addUserId,'create_time' => $currentTime]);                         
                        if($userProjResult){
                            $toEmail = Db::name("user")->where('id',$returnUserId)->value('email');
                            $subject = "【项慕吧】通知消息";
                            $message = bar_get_action_email($userId,$name,$projId,3);
                            $token = bar_get_user_token($returnUserId);
                            if(!$token) return 1;
                            $emailResult = bar_send_email($toEmail,$subject,$message,$token);
                            if(!$emailResult['error']){
                                $logResult = bar_action_email_log($userId,2);
                                // $this->success("接受成功！您可以在个人中心->我的项目里查看有关详细信息");
                                return 0;
                            }else{
                                // $this->error("内部错误：".$emailResult['msg'].",如果一直出现此问题，请反馈给我们");
                                return 6;
                            }
                        }else{
                            // $this->error("接受失败：添加用户到项目组失败,如果您一直遇到此问题，请尽快反馈给我们");
                            return 1;
                        }
                    }else{
                        // $this->error("接受失败：发送回复消息失败，如果您一直遇到此问题，请尽快反馈给我们");
                        return 2;
                    }
                }else{
                    // $this->error("接受失败：更新消息处理状态失败,如果您一直遇到此问题，请尽快反馈给我们");
                    return 3;
                }
            }else{
                // $this->error("操作授权错误,请不要搞事，谢谢合作");
                return 4;
            }
        }else{
            // $this->error("不受理的访问");
            return 5;
        }
    }

    /**
     * 拒绝请求
     * TODO 加上拒绝原因，帮助ta更好的成长
     */
    public function refuse($id='')
    {
        if($id){
            $userId = bar_get_user_id();
            $name = empty(session('user.nickname')) ? session('user.username') : session('user.nickname'); 
            
            $msgQuery = Db::name("message");
            $msg = $msgQuery->where('id',$id)->find();
            if(!$msg){
                // $this->error("该申请/邀请消息不存在");
                return 1;
            }
            $returnUserId = $msg['from_id'];
            $projId = $msg['proj_id'];
            if($msg['to_id'] == $userId){
                $msgUpdateResult = $msgQuery->where('id',$id)->update(['has_handle' => 2]);
                if($msgUpdateResult){
                    $currentTime = time();
                    $msgInsertResult = $msgQuery->insert([
                        'from_id' => $userId,
                        'to_id' => $returnUserId,
                        'proj_id' => $projId,
                        'type' => 4,
                        'send_time' => $currentTime
                    ]);
                    if($msgInsertResult){
                        $toEmail = Db::name("user")->where('id',$returnUserId)->value('email');                        
                        $subject = "【项慕吧】通知消息";
                        $message = bar_get_action_email($userId,$name,$projId,4);
                        $token = bar_get_user_token($returnUserId);
                        if(!$token) return 2;
                        $emailResult = bar_send_email($toEmail,$subject,$message,$token);
                        if(!$emailResult['error']){
                            $logResult = bar_action_email_log($userId,2);
                            // $this->success("已拒绝该请求，感谢您的严格把关，祝您找到更好的人才和项目！");
                            return 0;
                        }else{
                            // $this->error("内部错误：".$emailResult['msg'].",如果一直出现此问题，请反馈给我们");
                            return $emailResult['error'];
                        }
                    }else{
                        // $this->error("已拒绝该请求，但回复消息发送失败，如果您一直遇到此问题，请尽快反馈给我们");
                        return 4;
                    }
                }else{
                    // $this->error("拒绝该请求失败：更新消息处理状态失败，如果您一直遇到此问题，请尽快反馈给我们");
                    return 5;
                }
            }else{
                // $this->error("操作授权错误,请不要搞事，谢谢合作");
                return 6;
            }

        }else{
            $this->error("不受理的访问");
            // return 4;
        }
    }
    
    /**
     * 确认消息
     */
    public function confirm($id='')
    {
        if($id){
            $userId = bar_get_user_id();
            $name = empty(session('user.nickname')) ? session('user.username') : session('user.nickname'); 
            
            $msgQuery = Db::name("message");
            $msg = $msgQuery->where('id',$id)->find();
            if(!$msg){
                // $this->error("该通知消息不存在");
                return 1;
            }
            if($msg['to_id'] == $userId){
                $msgUpdateResult = $msgQuery->where('id',$id)->update(['has_handle' => 1]);
                if($msgUpdateResult){
                    // $this->success("确认消息成功！");
                    return 0;
                }else{
                    // $this->error("确认消息失败！");
                    return 2;
                }
            }else{
                // $this->error("操作授权错误,请不要搞事，谢谢合作");
                return 3;
            }

        }else{
            // $this->error("未受理的请求");
            return 4;
        }
    }

    /**
     * 删除项目(发起人权利)
     */
    public function delete($id){
        if($id == '') return -1;
        $userId = bar_get_user_id();
        $projQuery = Db::name("project");
        $userProjQuery = Db::name("user_proj");
        $projTagQuery = Db::name("proj_tag");
        $projSkillQuery = Db::name("proj_skill");
        
        $proj = $projQuery->where('id',$id)->find();
        if($userId != $proj['leader_id']){
            return -2;//没有权利！
        }
        $projTagFind = $projTagQuery->where('proj_id',$id)->find();
        $projResult = $projQuery->where('id',$id)->delete();
        if($projResult){
            $projTagResult = $projTagQuery->where('proj_id',$id)->delete();
            if($projTagResult || !$projTagFind){
                $projSkillResult = $projSkillQuery->where('proj_id',$id)->delete();
                $userProjResult = $userProjQuery->where('proj_id',$id)->delete();
                if($projSkillResult && $userProjResult){
                    return 0;
                }else{
                    return 3;
                }
            }else{
                return 2;
            }
        }else{
            return 1;
        }
    }

    /**
     * 修改项目信息(TODO)分离一下，在模型层处理数据
     */
    public function edit($id='')
    {  
        if($id='') return -1;
        $userId = bar_get_user_id();
        $projQuery = Db::name("proj");
        $cateQuery = Db::name("category");
        $projTagQuery = Db::name("proj_tag");
        $projSkillQuery = Db::name("proj_skill");
        $roleQuery = Db::name("role");
        $tagQuery = Db::name("tag");
        
        $this->redirect($this->request->root().'/');
    }

    /**
     * 退出项目(成员/非发起人选项)
     */
    public function quit($id = '')
    {
        if($id == '') return -1;
        $userId = bar_get_user_id();
        $userProjQuery = Db::name("user_proj");
        $userProjFind = $userProjQuery
            ->where('user_id',$userId)
            ->where('proj_id',$id)
            ->find();
        if($userProjFind){
            $delete = $userProjQuery->where('id',$userProjFind['id'])->delete();
            //TODO 对发起人在个人中心进行提醒
            if($delete){
                return 0;
            }else{
                return 2;
            }
        }else{
            return 1;
        }
    }

    /**
     * 踢出成员(发起人功能) TODO对被踢出成员在个人中心进行提醒
     */
    public function dismiss($pid,$uid)
    {   
        if($pid && $uid){
            $userId = bar_get_user_id();
            $userProjQuery = Db::name("user_proj");
            $projQuery = Db::name("project");
            $projLeaderId = $projQuery->where('id',$pid)->value('leader_id');
            if($userId == $projLeaderId){
                $userProjFind = $userProjQuery
                    ->where('proj_id',$pid)
                    ->where('user_id',$uid)
                    ->find();
                if($userProjFind){
                    $delete = $userProjQuery->where('id',$userProjFind['id'])->delete();
                    if($delete){
                        return 0;//success
                    }else{
                        return 2;//删除数据失败
                    }
                }else{
                    return 1;//该用户不在该项目中
                }
            }else{
                return -2; //不是创建者，没有权利
            }
        }else{
            return -1;//没有参数，错误请求
        }
    }

    
}