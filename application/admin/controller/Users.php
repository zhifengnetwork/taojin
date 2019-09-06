<?php

namespace app\admin\controller;

use app\common\model\Users as UsersModel;
use app\common\util\jwt\JWT;
use think\Db;
use think\Session;

class Users extends Common
{
    //会员列表
    public function index(){
        if(request()->isPost()){

            $encodekey = config("encodekey");

            $key = input('post.key');
            $page = input('page') ? input('page') : 1;
            $pageSize = input('limit') ? input('limit') : config('pageSize');

            /*$list=db('users')->alias('u')
                ->join(config('database.prefix').'user_level ul','u.level = ul.level_id','left')
                ->field(' decode(u.balance,"'.$encodekey.'") as balance , decode(u.all_balance,"'.$encodekey.'") as all_balance , decode(u.integral,"'.$encodekey.'") as integral , decode(u.all_integral,"'.$encodekey.'") as all_integral , u.email , u.id , u.username , u.is_lock , u.mobile , u.active_time , u.level , u.reg_time ,ul.level_name')
                ->where('u.email|u.mobile|u.username','like',"%".$key."%")
                ->order('u.id desc')
                ->paginate(array('list_rows'=>$pageSize,'page'=>$page))
                ->toArray();*/
            $url=Db::name('config')->where(['name'=>'login_url','inc_type'=>'taojin'])->value('value');
            $list = db('users')->alias('u')
                ->join(config('database.prefix') . 'user_level ul','u.level = ul.level_id','left')
                ->join(config('database.prefix') . 'users p','p.id=u.p_1','LEFT')
                ->field('u.id,u.nick_name,u.balance,u.recharge_balance,u.lock_balance,u.integral,u.currency,ul.level_name,u.phone,u.add_time,p.phone as p_phone')
                ->where('u.phone|u.nick_name|u.id','like',"%" . $key . "%")
                ->order('u.id desc')
                ->paginate(array('list_rows' => $pageSize,'page' => $page))
                ->toArray();
            foreach ($list['data'] as $key=>$value){
                if(!$list['data'][$key]['level_name']){
                    $list['data'][$key]['level_name']='矿工';
                }
//                $list['data'][$key]['user_url']=$url;
//                https://www.519991.cn?SimulatedLoginToken={{d.token}}
                $list['data'][$key]['token']=$this->create_token($value['id']);
                $list['data'][$key]['token']=$url.'?SimulatedLoginToken='.$list['data'][$key]['token'];
                $list['data'][$key]['add_time']=date('Y-m-d H:i:s',$value['add_time']);

            }
            return $result = ['code' => 0,'msg' => '获取成功!','data' => $list['data'],'count' => $list['total'],'rel' => 1];
        }
        return $this->fetch();
    }
    /**
     * 生成token
     */
    public function create_token($user_id){
        $time = time();
        $payload = array(
            "iss"=> "QUANMINTAOJIN",
            "iat"=> $time ,
            "exp"=> $time + 36000 ,
            "user_id"=> $user_id
        );
        $key = 'QUANMINTAOJIN';
        $token = JWT::encode($payload, $key, $alg = 'HS256', $keyId = null, $head = null);
        return $token;
    }
    //设置会员状态
    public function usersState(){
        $id = input('post.id');
        $is_lock = input('post.is_lock');
        if(db('users')->where('id=' . $id)->update(['is_lock' => $is_lock]) !== false){
            return ['status' => 1,'msg' => '设置成功!'];
        }else{
            return ['status' => 0,'msg' => '设置失败!'];
        }
    }

    public function edit($id = ''){
        if(request()->isPost()){
            $user = db('users');
            $data = input('post.');
            $level = explode(':',$data['level']);
            $data['level'] = $level[1];
            $province = explode(':',$data['province']);
            $data['province'] = $province[1];
            $city = explode(':',$data['city']);
            $data['city'] = $city[1];
            $district = explode(':',$data['district']);
            $data['district'] = $district[1];
            if(empty($data['password'])){
                unset($data['password']);
            }else{
                $data['password'] = md5($data['password']);
            }
            if($user->update($data) !== false){
                $result['msg'] = '会员修改成功!';
                $result['url'] = url('index');
                $result['code'] = 1;
            }else{
                $result['msg'] = '会员修改失败!';
                $result['code'] = 0;
            }
            return $result;
        }else{
            $province = db('Region')->where(array('pid' => 1))->select();
            $user_level = db('user_level')->order('sort')->select();
            $info = UsersModel::get($id);

            $info = $info->toArray();

            //矫正二进制报错的问题
            unset($info['balance']);
            unset($info['all_balance']);
            unset($info['integral']);
            unset($info['all_integral']);

            $this->assign('info',json_encode($info,true));
            $this->assign('title',lang('edit') . lang('user'));
            $this->assign('province',json_encode($province,true));
            $this->assign('user_level',json_encode($user_level,true));

            $city = db('Region')->where(array('pid' => $info['province']))->select();
            $this->assign('city',json_encode($city,true));
            $district = db('Region')->where(array('pid' => $info['city']))->select();
            $this->assign('district',json_encode($district,true));
            return $this->fetch();
        }
    }


    public function getRegion(){
        $Region = db("region");
        $pid = input("pid");
        $arr = explode(':',$pid);
        $map['pid'] = $arr[1];
        $list = $Region->where($map)->select();
        return $list;
    }

    //leapmary//会员就是这么直接删除
    public function usersDel(){
        db('users')->delete(['id' => input('id')]);
        $t = db()->getLastSql();
        return $result = ['code' => 1,'msg' => '删除成功!'.$t];
    }

    public function delall(){
        $map['id'] = array('in',input('param.ids/a'));
        db('users')->where($map)->delete();

        $result['msg'] = '删除成功！';
        $result['code'] = 1;
        $result['url'] = url('index');
        return $result;
    }

    /***********************************代理等级***********************************/
    public function userGroup(){
        if(request()->isPost()){
            $userLevel = db('user_level');
            $list = $userLevel->order('sort')->select();
            return $result = ['code' => 0,'msg' => '获取成功!','data' => $list,'rel' => 1];
        }
        return $this->fetch();
    }

    public function groupAdd(){
        if(request()->isPost()){
            $data = input('post.');
//            $data['open'] = input('post.open') ? input('post.open') : 0;
            db('user_level')->insert($data);
            $result['msg'] = '代理等级添加成功!';
            $result['url'] = url('userGroup');
            $result['code'] = 1;
            return $result;
        }else{
            $this->assign('title',lang('add') . "代理等级");
            $this->assign('info','null');
            return $this->fetch('group_form');
        }
    }

    public function groupEdit(){
        cache('user_level',[]);
        cache('user_level_money',[]);
        /*print_r(cache('user_level'));exit;*/
        if(request()->isPost()){

            $data = input('post.');
            db('user_level')->update($data);
            $result['msg'] = '代理等级修改成功!';
            $result['url'] = url('userGroup');
            $result['code'] = 1;
            return $result;
        }else{
            $map['level_id'] = input('param.level_id');
            $info = db('user_level')->where($map)->find();
            $this->assign('title',lang('edit') . "代理等级");
            $this->assign('info',json_encode($info,true));
            return $this->fetch('group_form');
        }
    }

    public function groupDel(){
        $level_id = input('level_id');
        if(empty($level_id)){
            return ['code' => 0,'msg' => '代理等级ID不存在！'];
        }
        db('user_level')->where(array('level_id' => $level_id))->delete();
        return ['code' => 1,'msg' => '删除成功！'];
    }

    public function groupOrder(){
        $userLevel = db('user_level');
        $data = input('post.');
        $userLevel->update($data);
        $result['msg'] = '排序更新成功!';
        $result['url'] = url('userGroup');
        $result['code'] = 1;
        return $result;
    }

    /**
     * 加减余额积分
     **/
    public function adminadd(){
        $user_id = input('id/d');
        $type = input('type/s');
        $user = Db::name('users')->field('nick_name,balance,recharge_balance,integral,currency')->where(['id'=>$user_id])->find();
        if(request()->isPost()){

            $num = input('num');
            $ifadd = input('ifadd/d');
            if(!$user||!in_array($type,['balance','integral','currency'])){
                return ['code' => 0,'msg' => '请求失败！'];
            }
            $data_num = $ifadd == 0?-$num:$num;
            Db::startTrans();
            $system_money=Db::name('system_money')->where('id',1)->find();
            if($type=='balance'){
                $new_balance = $ifadd==0?bcsub($user['recharge_balance'],$num,2):bcadd($user['recharge_balance'],$num,2);
                $data = ['recharge_balance'=>$new_balance>0?$new_balance:0];
                $system_money['balance']=$ifadd==1?bcsub($system_money['balance'],$num,2):bcadd($system_money['balance'],$num,2);
                if($system_money['balance']<0){
                    Db::rollback();
                    return ['code' => 0,'msg' => '系统金沙不足，请联系管理员！'];
                }
                $system_data['balance']=$ifadd==1?-$num:$num;
                $system_data['new_balance']=$system_money['balance'];
                $system_data['add_time']=time();
                $system_data['desc']='后台充值修改系统金额';
                $sys_id=Db::name('system_money_log')->insertGetId($system_data);
                if(!$sys_id){
                    Db::rollback();
                    return ['code' => 0,'msg' => '修改失败！,生成系统log出错!'];
                }
                $re=Db::name('system_money')->update($system_money);
                if(!$re){
                    Db::rollback();
                    return ['code' => 0,'msg' => '修改失败！'];
                }
                $res = Db::name('moneydetail')->insert([
                    'createtime' => time(),
                    'user_id' => $user_id,
                    'be_user_id' => $user_id,
                    'order_id' => 0,
                    'money' => $data_num,
                    'intro' => Session::get('username')."管理员操作{$data_num}",
                    'type' => 6,
                    'balance' => $user['recharge_balance']
                ]);
            }elseif($type=='integral'){
                $new_balance = $ifadd==0?$user['integral']-$num:$user['integral']+$num;
                $data = ['integral'=>$new_balance>0?$new_balance:0];
                $system_money['integral']=$ifadd==1?bcsub($system_money['integral'],$num,2):bcadd($system_money['integral'],$num,2);
                if($system_money['integral']<0){
                    Db::rollback();
                    return ['code' => 0,'msg' => '系统糖果不足，请联系管理员！'];
                }
                $system_data['integral']=$ifadd==1?-$num:$num;
                $system_data['new_integral']=$system_money['integral'];
                $system_data['add_time']=time();
                $system_data['desc']='后台充值修改系统金额';
                $sys_id=Db::name('system_money_log')->insertGetId($system_data);
                if(!$sys_id){
                    Db::rollback();
                    return ['code' => 0,'msg' => '修改失败！,生成系统log出错!'];
                }
                $re=Db::name('system_money')->update($system_money);
                if(!$re){
                    Db::rollback();
                    return ['code' => 0,'msg' => '修改失败！'];
                }
                $res =Db::name('integral')->insert([
                    'createtime' => time(),
                    'u_id' => $user_id,
                    'u_name' => $user['nick_name'],
                    'intro'=>Session::get('username')."管理员操作{$data_num}",
                    'integral' => $data_num,
                    'type' => 8,
                    'then_integral' => $user['integral']
                ]);
            }elseif($type=='currency'){
                $new_balance = $ifadd==0?$user['currency']-$num:$user['currency']+$num;
                $data = ['currency'=>$new_balance>0?$new_balance:0];
                $system_money['currency']=$ifadd==1?bcsub($system_money['currency'],$num,2):bcadd($system_money['currency'],$num,2);
                if($system_money['currency']<0){
                    Db::rollback();
                    return ['code' => 0,'msg' => '系统币不足，请联系管理员！'];
                }
                $system_data['currency']=$ifadd==1?-$num:$num;
                $system_data['new_currency']=$system_money['currency'];
                $system_data['add_time']=time();
                $system_data['desc']='后台充值修改系统金额';
                $sys_id=Db::name('system_money_log')->insertGetId($system_data);
                if(!$sys_id){
                    Db::rollback();
                    return ['code' => 0,'msg' => '修改失败！,生成系统log出错!'];
                }
                $re=Db::name('system_money')->update($system_money);
                if(!$re){
                    Db::rollback();
                    return ['code' => 0,'msg' => '修改失败！'];
                }
                $res =Db::name('users_currency')->insert([
                    'add_time' => time(),
                    'user_id' => $user_id,
                    'user_name' => $user['nick_name'],
                    'currency' => $data_num,
                    'type' => 2,
                    'old_currency' => $user['currency'],
                    'desc'=>Session::get('username').'管理员操作'
                ]);
            }
            $res&&$res = Db::name('users')->where(['id'=>$user_id])->update($data);
            if($res){
                Db::commit();
                return ['code' => 1,'msg' => '修改成功！','url'=>url('index')];
            }else{
                Db::rollback();
                return ['code' => 0,'msg' => '修改失败！'];
            }

        }
        $this->assign('id',$user_id);
        $this->assign('value',$user[$type]);
        $this->assign('type',$type);

        return $this->fetch('adminadd');
    }
    /*
     * 挂卖币列表
     */
    public function user_auction(){
        if(request()->isPost()){

            $encodekey = config("encodekey");

            $key = input('post.key');
            $page = input('page') ? input('page') : 1;
            $pageSize = input('limit') ? input('limit') : config('pageSize');

            /*$list=db('users')->alias('u')
                ->join(config('database.prefix').'user_level ul','u.level = ul.level_id','left')
                ->field(' decode(u.balance,"'.$encodekey.'") as balance , decode(u.all_balance,"'.$encodekey.'") as all_balance , decode(u.integral,"'.$encodekey.'") as integral , decode(u.all_integral,"'.$encodekey.'") as all_integral , u.email , u.id , u.username , u.is_lock , u.mobile , u.active_time , u.level , u.reg_time ,ul.level_name')
                ->where('u.email|u.mobile|u.username','like',"%".$key."%")
                ->order('u.id desc')
                ->paginate(array('list_rows'=>$pageSize,'page'=>$page))
                ->toArray();*/

//            $list = db('users')->alias('u')
//                ->join(config('database.prefix') . 'user_level ul','u.level = ul.level_id','left')
//                ->where('u.phone|u.nick_name|u.id','like',"%" . $key . "%")
//                ->order('u.id desc')
//                ->paginate(array('list_rows' => $pageSize,'page' => $page))
//                ->toArray();
//            foreach ($list['data'] as $key=>$value){
//                if(!$list['data'][$key]['level_name']){
//                    $list['data'][$key]['level_name']='普通会员';
//                }
//                $list['data'][$key]['add_time']=date('Y-m-d H:i:s',$value['add_time']);
//
//            }
            $list=Db::name('auction')->alias('a')
                ->join('users u','u.id=a.user_id','LEFT')
                ->field('a.id,a.user_id,a.currency_num,a.currency_money,a.all_money,a.add_time,u.nick_name')
                ->paginate(array('list_rows' => $pageSize,'page' => $page));
            $list=$list->toArray();
            foreach ($list['data'] as $k=>$v){
                $list['data'][$k]['add_time']=date('Y-m-d H:i:s',$v['add_time']);
            }
            return $result = ['code' => 0,'msg' => '获取成功!','data' => $list['data'],'count' => $list['total'],'rel' => 1];
        }
        return $this->fetch();
    }

}