<?php

namespace app\admin\controller;

use app\admin\model\Users as UsersModel;

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

            $list = db('users')->alias('u')
                ->join(config('database.prefix') . 'user_level ul','u.level = ul.level_id','left')
                ->where('u.phone|u.nick_name|u.tb_nickname','like',"%" . $key . "%")
                ->order('u.id desc')
                ->paginate(array('list_rows' => $pageSize,'page' => $page))
                ->toArray();

            foreach($list['data'] as $k => $v){
                unset($list['data'][$k]['balance']);
                unset($list['data'][$k]['all_balance']);
                unset($list['data'][$k]['integral']);
                unset($list['data'][$k]['all_integral']);
            }

            return $result = ['code' => 0,'msg' => '获取成功!','data' => $list['data'],'count' => $list['total'],'rel' => 1];
        }
        return $this->fetch();
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

    /***********************************会员组***********************************/
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
            $data['open'] = input('post.open') ? input('post.open') : 0;
            db('user_level')->insert($data);
            $result['msg'] = '会员组添加成功!';
            $result['url'] = url('userGroup');
            $result['code'] = 1;
            return $result;
        }else{
            $this->assign('title',lang('add') . "会员组");
            $this->assign('info','null');
            return $this->fetch('groupForm');
        }
    }

    public function groupEdit(){
        cache('user_level',[]);
        cache('user_level_money',[]);
        /*print_r(cache('user_level'));exit;*/
        if(request()->isPost()){

            $data = input('post.');
            db('user_level')->update($data);
            $result['msg'] = '会员组修改成功!';
            $result['url'] = url('userGroup');
            $result['code'] = 1;
            return $result;
        }else{
            $map['level_id'] = input('param.level_id');
            $info = db('user_level')->where($map)->find();
            $this->assign('title',lang('edit') . "会员组");
            $this->assign('info',json_encode($info,true));
            return $this->fetch('groupForm');
        }
    }

    public function groupDel(){
        $level_id = input('level_id');
        if(empty($level_id)){
            return ['code' => 0,'msg' => '会员组ID不存在！'];
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

        if(request()->isPost()){

            $num = input('num');
            $ifadd = input('ifadd');
            $id = input('id'); //用户id
            $type = input('type');

            /*print_r($num);exit;*/

            $user_info = action('common/User/getuserinfo',['token' => '','u_id' => $id]);
            $data['user_info'] = $user_info;

            if($ifadd == 0){
                $num = -$num;
            }
            //加金额
            if($type == 'money'){
                //获取用户信息
                $data['num'] = $num;
                //管理员操作
                $re = action('common/User/addmoney',['type' => 6,'data' => $data]);

            }else{
                //加积分
                $data['user_id'] = $id;
                $data['integral_s'] = $num;
                $re = action('common/User/addintegral',['type' => 'admin_add','data' => $data]);

            }

            print_r($re);
            exit;

        }else{

            $id = input('id');
            $type = input('type');

            $this->assign("id",$id);
            $this->assign("type",$type);

            return $this->fetch('adminadd');

        }


    }


}