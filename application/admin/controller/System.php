<?php
namespace app\admin\controller;
use think\Db;
use think\Request;
use clt\Leftnav;
use app\admin\model\System as SysModel;
class System extends Common
{
    //站点设置
    public function system($sys_id=1){
        $table = db('system');
        if(request()->isPost()) {

            cache('config_system', null);

            $datas = input('post.');
            unset($datas['file']);
            if($table->where('id',1)->update($datas)!==false) {
                savecache('System');
                return json(['code' => 1, 'msg' => '站点设置保存成功!', 'url' => url('system/system')]);
            } else {
                return json(array('code' => 0, 'msg' =>'站点设置保存失败！'));
            }
        }else{
            $system = $table->field('id,name,url,title,money,key,des,bah,copyright,ads,tel,email,logo,uid,yxspw,yxs_dx_pwd,weixin,weixin_url,notice')->find($sys_id);
            if($system['logo'])
            {
                $system['logo_url']=SITE_URL.__PUBLIC__.$system['logo'];
            }
            $this->assign('system', $system);
            return $this->fetch();
        }
    }
    //邮箱配置
    public function email(){
        $table = db('config');
        if(request()->isPost()) {
            $datas = input('post.');
            foreach ($datas as $k=>$v){
                $table->where(['name'=>$k,'inc_type'=>'smtp'])->update(['value'=>$v]);
            }
            return json(['code' => 1, 'msg' => '邮箱设置成功!', 'url' => url('system/email')]);
        }else{
            $smtp = $table->where(['inc_type'=>'smtp'])->select();
            $info = convert_arr_kv($smtp,'name','value');
            $this->assign('info', $info);
            return $this->fetch();
        }
    }

    //发送邮件
    public function trySend(){
        $sender = input('email');
        //检查是否邮箱格式
        if (!is_email($sender)) {
            return json(['code' => -1, 'msg' => '测试邮箱码格式有误']);
        }
        $send = send_email($sender, '测试邮件', '您好！这是一封来自'.$this->system['name'].'的测试邮件！');
        if ($send) {
            return json(['code' => 1, 'msg' => '邮件发送成功！']);
        } else {
            return json(['code' => -1, 'msg' => '邮件发送失败！']);
        }
    }

    //淘宝客配置
    public function taobaoke(){
        $table = db('config');
        if(request()->isPost()) {

            cache('config_taobaoke', null);

            $datas = input('post.');

            foreach ($datas as $k=>$v){
                $table->where(['name'=>$k,'inc_type'=>'taobaoke'])->update(['value'=>$v]);
            }
            return json(['code' => 1, 'msg' => '设置成功!', 'url' => url('system/taobaoke')]);
        }else{
            $smtp = $table->where(['inc_type'=>'taobaoke'])->select();
            $info = convert_arr_kv($smtp,'name','value');
            $this->assign('info', $info);
            return $this->fetch();
        }
    }

    //京东api
    public function jingdongke(){
        $table = db('config');
        if(request()->isPost()) {

            cache('config_jingdongke', null);

            $datas = input('post.');

            foreach ($datas as $k=>$v){
                $table->where(['name'=>$k,'inc_type'=>'jingdongke'])->update(['value'=>$v]);
            }
            return json(['code' => 1, 'msg' => '设置成功!', 'url' => url('system/jingdongke')]);
        }else{
            $smtp = $table->where(['inc_type'=>'jingdongke'])->select();
            $info = convert_arr_kv($smtp,'name','value');
            $this->assign('info', $info);
            return $this->fetch();
        }
    }

    /**
     *app运营设置
     **/
    public function app(){
        $table = db('config');
        if(request()->isPost()) {

            cache('config_app', null);

            $datas = input('post.');

            foreach ($datas as $k=>$v){
                $table->where(['name'=>$k,'inc_type'=>'app'])->update(['value'=>$v]);
            }
            return json(['code' => 1, 'msg' => '设置成功!', 'url' => url('system/app')]);
        }else{
            $smtp = $table->where(['inc_type'=>'app'])->select();
            $info = convert_arr_kv($smtp,'name','value');
            $this->assign('info', $info);
            return $this->fetch();
        }
    }
    /**
     *淘金设置
     **/
    public function taojin(){
        $table = db('config');
        if(request()->isPost()) {

            cache('taojin', null);

            $datas = input('post.');
            unset($datas['file']);

            foreach ($datas as $k=>$v){
                if($k=='currency'){
                    $yesterday_time=$table->where(['inc_type'=>'taojin','name'=>'yesterday_time'])->value('value');
                    $currency=$table->where(['inc_type'=>'taojin','name'=>'currency'])->value('value');
                    $yesterday_time=strtotime($yesterday_time);
                    $tomorrow=date('Y-m-d ',strtotime('+1 day')).'00:00:00';
                    if($yesterday_time<time()){
                        $table->where(['name'=>'yesterday_currency','inc_type'=>'taojin'])->update(['value'=>$currency]);
                        $table->where(['name'=>'yesterday_time','inc_type'=>'taojin'])->update(['value'=>$tomorrow]);
                    }
                }
                $table->where(['name'=>$k,'inc_type'=>'taojin'])->update(['value'=>$v]);
            }
            return json(['code' => 1, 'msg' => '设置成功!', 'url' => url('system/taojin')]);
        }else{
            $smtp = $table->where(['inc_type'=>'taojin'])->select();
            $info = convert_arr_kv($smtp,'name','value');
            if($info['music_url'])
            {
                $info['music']=$info['music_url'];
                $info['music_url']=SITE_URL.__PUBLIC__.$info['music_url'];
            }
            $this->assign('info', $info);
            return $this->fetch();
        }
    }
    /**
     *中奖设置
     **/
    public function reward(){
        $table = db('config');
        if(request()->isPost()) {

            cache('reward', null);

            $datas = input('post.');
            unset($datas['file']);

            foreach ($datas as $k=>$v){
                $table->where(['name'=>$k,'inc_type'=>'reward'])->update(['value'=>$v]);
            }
            return json(['code' => 1, 'msg' => '设置成功!', 'url' => url('system/reward')]);
        }else{
            $smtp = $table->where(['inc_type'=>'taojin'])->select();
            $info = convert_arr_kv($smtp,'name','value');
            $this->assign('info', $info);
            return $this->fetch();
        }
    }
    /**
     *中奖设置
     **/
//    public function user_reward(){
//        $table = db('config');
//        if(request()->isPost()) {
//
//            cache('reward', null);
//
//            $datas = input('post.');
//            unset($datas['file']);
//
//            foreach ($datas as $k=>$v){
//                $table->where(['name'=>$k,'inc_type'=>'reward'])->update(['value'=>$v]);
//            }
//            return json(['code' => 1, 'msg' => '设置成功!', 'url' => url('system/reward')]);
//        }else{
//            $smtp = $table->where(['inc_type'=>'taojin'])->select();
//            $info = convert_arr_kv($smtp,'name','value');
//            $this->assign('info', $info);
//            return $this->fetch();
//        }
//    }
    public function user_reward(){
        if(request()->isPost()){
            $userLevel = db('set_reward');
            $list = $userLevel->order('id')->select();
            return $result = ['code' => 0,'msg' => '获取成功!','data' => $list,'rel' => 1];
        }
        return $this->fetch();
    }
    public function rewardAdd(){
        if(request()->isPost()){
            $data = input('post.');
            $user=M('users')->where('id',$data['user_id'])->find();
            if(!$user){
                $result['msg'] = '用户id不存在，请重新输入!';
                $result['code'] = 0;
                return $result;
            }
            $set_reward=M('set_reward')->where('user_id',$data['user_id'])->find();
            if($set_reward){
                $result['msg'] = '该用户已存在，请去修改!';
                $result['code'] = 0;
                return $result;
            }
            if($data['num']<0){
                $result['msg'] = '中奖数量不能为负数!';
                $result['code'] = 0;
                return $result;
            }
            if($data['num']>100){
                $result['msg'] = '中奖数量不能大于100!';
                $result['code'] = 0;
                return $result;
            }
            db('set_reward')->insert($data);
            $result['msg'] = '添加成功!';
            $result['url'] = url('user_reward');
            $result['code'] = 1;
            return $result;
        }else{
            $this->assign('title',lang('add') . "中奖ID");
            $this->assign('info','null');
            return $this->fetch('reward_edit');
        }
    }
    public function rewardEdit(){
        if(request()->isPost()){
            $data = input('post.');
            $user=M('users')->where('id',$data['user_id'])->find();
            if(!$user){
                $result['msg'] = '用户id不存在，请重新输入!';
                $result['code'] = 0;
                return $result;
            }
            $set_reward=M('set_reward')->where('user_id',$data['user_id'])->find();
            if($set_reward&&$set_reward['id']!=$data['id']){
                $result['msg'] = '该用户已存在，请去该用户下修改!';
                $result['code'] = 0;
                return $result;
            }
            if($data['num']<0){
                $result['msg'] = '中奖数量不能为负数!';
                $result['code'] = 0;
                return $result;
            }
            if($data['num']>100){
                $result['msg'] = '中奖数量不能大于100!';
                $result['code'] = 0;
                return $result;
            }
            db('set_reward')->update($data);
            $result['msg'] = '修改成功!';
            $result['url'] = url('user_reward');
            $result['code'] = 1;
            return $result;
        }else{
            $map['id'] = input('param.id');
            $info = db('set_reward')->where($map)->find();
            $this->assign('title',lang('edit') . "中奖ID");
            $this->assign('info',json_encode($info,true));
            return $this->fetch('reward_edit');
        }
    }
}
