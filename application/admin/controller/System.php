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
                $system['logo']=SITE_URL.__PUBLIC__.$system['logo'];
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


}
