<?php

namespace app\common\controller;

use think\Controller;
use think\db;

class Alluse extends Controller
{
    public function _initialize(){
        parent::_initialize();
    }

    /**
     * $confing = action('common/Alluse/getconfig',['inc_type'=>'app']);
     * 获取app返利的配置信息
     **/
    public function getconfig($inc_type){
        //leapmary//##缓存机制###################//
        $dataconfig = cache('config_'.$inc_type);

        if(empty($dataconfig)){
            $dataconfig = db('config')->where('inc_type',$inc_type)->select();
            $dataconfig = convert_arr_kv($dataconfig,'name','value');
            cache('config_'.$inc_type,$dataconfig);
        }
        //leapmary//##缓存机制###################//
        return $dataconfig;
    }

    /**
     * $confing = action('common/Alluse/get_extensionbit',['id'=>'1']);
     * 获取推广位信息
     **/
    public function get_extensionbit($id){
        //leapmary//##缓存机制###################//
        $data = cache('extensionbit_'.$id);
        if(empty($data)){
            $data = db('extensionbit')->where('id',$id)->find();
            /*$data = $data['adzoneid'];*/
            cache('extensionbit_'.$id,$data);
        }
        //leapmary//##缓存机制###################//
        return $data;
    }

}































