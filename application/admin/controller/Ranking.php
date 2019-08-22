<?php

namespace app\admin\controller;

use think\Db;

class Ranking extends Common
{
    public function index(){

        if(request()->isPost()){
            $keyword = input('post.key');
            $page = input('page') ? input('page') : 1;
            $pageSize = input('limit') ? input('limit') : config('pageSize');

            if(!empty($keyword)){
                $map['id'] = array('like','%' . $keyword . '%');
            }
            $map['is_delete'] = 0;

            $list = Db::name('ranking')
                ->where($map)
                ->order('id desc')
                ->paginate(array('list_rows' => $pageSize,'page' => $page))
                ->toArray();

            return [
                'code' => 0,
                'msg' => '获取成功',
                'data' => $list['data'],
                'count' => $list['total'],
                'rel' => 1,
            ];

        }else{
            return $this->fetch('ranking/index');
        }
    }

    public function listDel(){
        $id = input('post.id');
        if(Db::name('ranking')->where(array('id' => $id))->update(['is_delete'=>1])){
            return ['code'=>1,'msg' => '删除成功！','url' => url('index')];
        }else{
            return ['code'=>0,'msg' => '删除失败！','url' => url('index')];
        }
    }

    public function delAll(){
        $map['id'] = array('in',input('param.ids/a'));
        if(Db::name('ranking')->where($map)->update(['is_delete'=>1])){
            return ['code'=>1,'msg' => '删除成功！','url' => url('index')];
        }else{
            return ['code'=>0,'msg' => '删除失败！','url' => url('index')];
        }
    }

    public function listorder(){
        $data = input('post.');
        if(Db::name('ranking')->update($data)){
            return ['code'=>1,'msg' => '排序成功！','url' => url('index')];
        }else{
            return ['code'=>0,'msg' => '排序失败！','url' => url('index')];
        }
    }
    /*
     * 包时间段设置
     */
    public function time_slot(){

        if(request()->isPost()){
            $keyword = input('post.key');
            $page = input('page') ? input('page') : 1;
            $pageSize = input('limit') ? input('limit') : config('pageSize');
            $map=[];
            if(!empty($keyword)){
                $map['id'] = array('like','%' . $keyword . '%');
            }

            $list = Db::name('time_slot')
                ->where($map)
                ->order('id desc')
                ->paginate(array('list_rows' => $pageSize,'page' => $page))
                ->toArray();
            foreach ($list['data'] as $key=>$value){
                $list['data'][$key]['add_time']=date('Y-m-d H:i:s',$list['data'][$key]['add_time']);
            }
            return [
                'code' => 0,
                'msg' => '获取成功',
                'data' => $list['data'],
                'count' => $list['total'],
                'rel' => 1,
            ];

        }else{
            return $this->fetch('ranking/time_slot');
        }
    }
    /*
     * 删除
     */
    public function timeDel(){
        $id = input('post.id');
        if(Db::name('time_slot')->where(array('id' => $id))->delete()){
            return ['code'=>1,'msg' => '删除成功！','url' => url('time_slot')];
        }else{
            return ['code'=>0,'msg' => '删除失败！','url' => url('time_slot')];
        }
    }
    /*
     * 是否启用
     */
    public function tiemState(){
        $id=input('post.id');
        $is_open=input('post.status');
        if (empty($id)){
            $result['status'] = 0;
            $result['info'] = 'ID不存在!';
            $result['url'] = url('time_slot');
            return $result;
        }
        db('time_slot')->where('id='.$id)->update(['status'=>$is_open]);
        $result['status'] = 1;
        $result['info'] = '用户状态修改成功!';
        $result['url'] = url('time_slot');
        return $result;
    }
    /*
     * 添加时间段
     */
    public function timeAdd(){
        if(request()->isPost()){
            $data = input('post.');
//            $data['open'] = input('post.open') ? input('post.open') : 0;
            $data['add_time']=time();
            db('time_slot')->insert($data);
            $result['msg'] = '时间段添加成功!';
            $result['url'] = url('time_slot');
            $result['code'] = 1;
            return $result;
        }else{
            $this->assign('title',lang('add') . "时间段");
            $this->assign('info','null');
            return $this->fetch('time_form');
        }
    }

    public function timeEdit(){
        if(request()->isPost()){
            $data = input('post.');
            db('time_slot')->update($data);
            $result['msg'] = '时间段修改成功!';
            $result['url'] = url('userGroup');
            $result['code'] = 1;
            return $result;
        }else{
            $map['id'] = input('param.id');
            $info = db('time_slot')->where($map)->find();
            $this->assign('title',lang('edit') . "时间段");
            $this->assign('info',json_encode($info,true));
            return $this->fetch('time_form');
        }
    }
}