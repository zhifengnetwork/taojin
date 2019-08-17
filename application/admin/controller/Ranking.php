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

}