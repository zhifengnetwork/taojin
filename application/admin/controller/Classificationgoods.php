<?php

namespace app\admin\controller;

use think\Db;
use clt\Leftnav;
use app\admin\model\classification;


class Classificationgoods extends Common
{

    /********************************淘宝客菜单管理*******************************/
    public function listClassificationgoods(){
        if(request()->isPost()){
            $nav = new Leftnav();
            $arr = cache('classificationList');
            if(!$arr){
                $authRule = classification::all(function($query){
                    $query->order('sort','asc');
                });
                $arr = $nav->menu($authRule);
                cache('classificationList',$arr,3600);
            }
            return $result = ['code' => 0,'msg' => '获取成功!','data' => $arr,'rel' => 1];
        }
        return view();
    }

    public function classificationgoodsAdd(){
        if(request()->isPost()){
            $data = input('post.');
            $data['addtime'] = time();
            classification::create($data);
            ////cache('authRule',null);
            cache('classificationList',null);
            return $result = ['code' => 1,'msg' => '分类添加成功!','url' => url('listClassificationgoods')];
        }else{
            $nav = new Leftnav();
            $arr = cache('classificationList');
            if(!$arr){
                $authRule = classification::all(function($query){
                    $query->order('sort','asc');
                });
                $arr = $nav->menu($authRule);
                cache('classificationList',$arr,3600);
            }
            $this->assign('admin_rule',$arr);//分类列表
            return $this->fetch();
        }
    }

    public function classificationgoodsOrder(){
        $classification = db('classification');
        $data = input('post.');
        if($classification->update($data) !== false){
            cache('classificationList',null);
            //cache('authRule',null);
            return $result = ['code' => 1,'msg' => '排序更新成功!','url' => url('listClassificationgoods')];
        }else{
            return $result = ['code' => 0,'msg' => '排序更新失败!'];
        }
    }

    //设置分类菜单显示或者隐藏
    public function classificationgoodsState(){
        $id = input('post.id');
        $menustatus = input('post.menustatus');
        if(db('classification')->where('id=' . $id)->update(['menustatus' => $menustatus]) !== false){
            //cache('authRule',null);
            cache('classificationList',null);
            return ['status' => 1,'msg' => '设置成功!'];
        }else{
            return ['status' => 0,'msg' => '设置失败!'];
        }
    }

    //设置分类是否验证
    public function classificationgoodsTz(){
        $id = input('post.id');
        $authopen = input('post.authopen');
        if(db('classification')->where('id=' . $id)->update(['authopen' => $authopen]) !== false){
            //cache('authRule',null);
            cache('classificationList',null);
            return ['status' => 1,'msg' => '设置成功!'];
        }else{
            return ['status' => 0,'msg' => '设置失败!'];
        }
    }

    public function classificationgoodsDel(){
        classification::destroy(['id' => input('param.id')]);
        //cache('authRule',null);
        cache('classificationList',null);
        return $result = ['code' => 1,'msg' => '删除成功!'];
    }

    public function classificationgoodsEdit(){
        if(request()->isPost()){
            $datas = input('post.');
            if(classification::update($datas)){
                //cache('authRule',null);
                cache('classificationList',null);
                return json(['code' => 1,'msg' => '保存成功!','url' => url('listClassificationgoods')]);
            }else{
                return json(['code' => 0,'msg' => '保存失败！']);
            }
        }else{
            $admin_rule = classification::get(function($query){
                $query->where(['id' => input('id')])->field('id,href,title,icon,sort,menustatus');
            });
            $this->assign('rule',$admin_rule);
            return $this->fetch();
        }
    }
}