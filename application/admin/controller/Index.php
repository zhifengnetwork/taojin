<?php

namespace app\admin\controller;

use think\Db;
use think\Input;

class Index extends Common
{
    public function _initialize(){
        parent::_initialize();
    }

    public function index(){

      
        //导航
        // 获取缓存数据
        $authRule = cache('authRule');
        if(!$authRule){
            $authRule = db('auth_rule')->where('menustatus=1')->order('sort')->select();
            cache('authRule',$authRule,3600);
        }

        //声明数组
        $menus = array();
        foreach($authRule as $key => $val){
            $authRule[$key]['href'] = url($val['href']);
            if($val['pid'] == 0){
                if(session('aid') != 1){
                    if(in_array($val['id'],$this->adminRules)){
                        $menus[] = $val;
                    }
                }else{
                    $menus[] = $val;
                }
            }
        }

        foreach($menus as $k => $v){
            foreach($authRule as $kk => $vv){
                if($v['id'] == $vv['pid']){
                    if(session('aid') != 1){
                        if(in_array($vv['id'],$this->adminRules)){
                            $menus[$k]['children'][] = $vv;
                        }
                    }else{
                        $menus[$k]['children'][] = $vv;
                    }
                }
            }
        }

        $this->assign('menus',json_encode($menus,true));
        return $this->fetch();

    }

    /**
     * 主显示统计页面
     **/
    public function main(){

        $version = Db::query('SELECT VERSION() AS ver');
        $config = [
            'url' => $_SERVER['HTTP_HOST'],
            'document_root' => $_SERVER['DOCUMENT_ROOT'],
            'server_os' => PHP_OS,
            'server_port' => $_SERVER['SERVER_PORT'],
            'server_ip' => $_SERVER['SERVER_ADDR'],
            'server_soft' => $_SERVER['SERVER_SOFTWARE'],
            'php_version' => PHP_VERSION,
            'mysql_version' => $version[0]['ver'],
            'max_upload_size' => ini_get('upload_max_filesize')
        ];
        $system_money=Db::name('system_money')->where('id',1)->find();
        $this->assign("system_money",$system_money);
        $user_all=Db::name('users')->field('SUM(balance) as balance,SUM(recharge_balance) as recharge_balance,SUM(integral) as integral,SUM(currency) as currency')
            ->where('id','neq','85')
            ->find();
        $user_all['money']=$user_all['balance']+$user_all['recharge_balance'];
        $this->assign("user_all",$user_all);
        $user_admin=Db::name('users')->field('balance')->where('id',880)->find();
        $this->assign("user_admin",$user_admin);
        $withdraw=Db::name('withdraw')->where('status',0)->sum('money');
        $this->assign("withdraw",$withdraw);
        $money_withdraw=Db::name('withdraw')->where('status',1)->sum('money');
        $this->assign("money_withdraw",$money_withdraw);
        //leapmary//###############################
        $classification = db('classification')->where("pid",0)->select();
        $classification = convert_arr_kv($classification,'id','title');
        /*print_r($classification);exit;*/
        //leapmary//###############################

        /*##三个统计###############################*/
        //总人数
        $userall = db('users')->count();

        $this->assign("userall",$userall);

        /*##三个统计###############################*/

        $this->assign('classification',$classification);
        $this->assign('config',$config);
        return $this->fetch();
    }

    public function navbar(){
        return $this->fetch();
    }

    public function nav(){
        return $this->fetch();
    }

    public function clear(){
        $R = RUNTIME_PATH;
        if($this->_deleteDir($R)){
            $result['info'] = '清除缓存成功!';
            $result['status'] = 1;
        }else{
            $result['info'] = '清除缓存失败!';
            $result['status'] = 0;
        }
        $result['url'] = url('admin/index/index');
        return $result;
    }

    private function _deleteDir($R){
        $handle = opendir($R);
        while(($item = readdir($handle)) !== false){
            if($item != '.' and $item != '..'){
                if(is_dir($R . '/' . $item)){
                    $this->_deleteDir($R . '/' . $item);
                }else{
                    if(!unlink($R . '/' . $item))
                        die('error!');
                }
            }
        }
        closedir($handle);
        return rmdir($R);
    }

    //退出登陆
    public function logout(){
        session(null);
        $this->redirect('login/index');
    }

}
