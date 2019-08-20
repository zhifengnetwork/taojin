<?php

namespace app\common\controller;

use think\Controller;

class Goods extends Controller
{
    public function _initialize(){
        parent::_initialize();
    }

    /**
     * 得到商品的所有信息包括返利，包括转链
     **/
    public function getgoodsinfo($where,$order){

        $goods = db("goods")
            ->where($where)
            ->order($order)
            ->limit(10)
            ->select();

        /*foreach($goods as $k => $v){

            $coupon_price = explode("减",$v['coupon_info']);
            $coupon_price = str_replace("元","",$coupon_price[1]);

            $goods[$k]['coupon_price'] = $coupon_price;

        }*/

        //print_r($goods);exit;
        return $goods;

    }

    /**
     * 统一计算商品佣金
     * 传入金额
     */
    public function getcommission($money,$rebate){

        //舍去取整
        return floor($money * $rebate) / 100;  //单位元

    }

    /**
     * 统一计算商品佣金
     * 传入金额
     */
    public function get_goods_from_ali_api($id){

        $couponget = db('couponget')->where("id = $id")->find();

        $config = db('config')->where("inc_type = 'taobaoke'")->select();
        $info = convert_arr_kv($config,'name','value');

        $appkey = $info['appkey'];
        $secret = $info['secretkey'];
        $sessionKey = $info['sessionkey'];

        $resp = require_once("./vendor/taobaokeapi/couponget.php");



        if(!empty($resp)){

            if(empty($resp[0]['num_iid'])){
                $newresp = [];
                $newresp[0] = $resp;
                //采集的商品有时候没有这个值
                if(empty($resp['item_description'])){
                    $newresp[0]['item_description'] = '';
                }else{
                    $newresp[0]['item_description'] = $resp['item_description'];
                }

                if(empty($resp['small_images'])){
                    $newresp[0]['small_images'] = '';
                }else{
                    $newresp[0]['small_images'] = json_encode($resp['small_images']);
                }

                //记录商品的分类
                $newresp[0]['cate_id'] = $couponget['cate_id'];
                $newresp[0]['two_cate_id'] = $couponget['two_cate_id'];

                $resp = $newresp;
            }else{

                foreach($resp as $k => $v){
                    //采集的商品有时候没有这个值
                    if(empty($v['item_description'])){
                        $resp[$k]['item_description'] = '';
                    }

                    if(empty($v['small_images'])){
                        $resp[$k]['small_images'] = '';
                    }else{
                        $resp[$k]['small_images'] = json_encode($v['small_images']);
                    }

                    //记录商品的分类
                    $resp[$k]['cate_id'] = $couponget['cate_id'];
                    $resp[$k]['two_cate_id'] = $couponget['two_cate_id'];
                }
            }

            $respdelarr = convert_arr_k_all($resp,'num_iid');

            //根据设置来决定是否,删除本规则采集商品
            if($couponget['emptyold']){
                db('goods')->where('cg_id',$id)->delete();
            }

            //同num_iid还是要删除的
            db('goods')->where('num_iid','in',$respdelarr)->delete();
            db('goods_all')->where('num_iid','in',$respdelarr)->delete();

            //提前计算出来
            foreach($resp as $k => $v){

                /*print_r($v);exit;*/
                $v['coupon_info'] = str_replace('元减',',',$v['coupon_info']);
                $v['coupon_info'] = str_replace('满','',$v['coupon_info']);
                $v['coupon_info'] = str_replace('元','',$v['coupon_info']);
                $v['coupon_info'] = explode(',',$v['coupon_info']);

                $resp[$k]['coupon_money'] = $v['coupon_info'][1];                          //优惠卷面额
                $resp[$k]['coupon_price'] = $v['zk_final_price'] - $resp[$k]['coupon_money'];//卷后价

                //佣金 = 卷后价*佣金比例
                $resp[$k]['commission'] = floor($resp[$k]['coupon_price'] * $v['commission_rate']) / 100;

                /*print_r($resp[$k]['commission'] );exit;*/

                $resp[$k]['cg_id'] = $id;

                db('goods')->insertGetId($resp[$k]);
                //全部保存库
                db('goods_all')->insertGetId($resp[$k]);
            }

            //批量插入会有很多问题，先暂停使用，改为一条条添加，等有时间再搞
            //$re = db('goods')->insertAll($resp);

            //#leapmary#获取淘口令//
            $goodstbk = $resp;
            require_once("./vendor/taobaokeapi/tpwdcreate.php");

        }

        return 1;
    }

    public function getonetbkgoods($arr){
        $dataone = [];

        if(!empty($arr['coupon_share_url'])){
            $dataone['coupon_click_url'] = $arr['coupon_share_url'];//商品优惠券推广链接
        }else{
            $dataone['coupon_click_url'] = $arr['url'];//商品优惠券推广链接
        }

        if(strpos($dataone['coupon_click_url'],'http') !== 0){
            $dataone['coupon_click_url'] = 'https:' . $dataone['coupon_click_url'];
        }

        $small_images = json_encode($arr['small_images']);
        $dataone['createtime'] = date("Y-m-d h:i:s");
        $dataone['category'] = $arr['category'];    //后台一级类目

        $dataone['coupon_end_time'] = $arr['coupon_end_time']; //优惠券结束时间
        $dataone['coupon_remain_count'] = $arr['coupon_remain_count'];//优惠券剩余量
        $dataone['coupon_start_time'] = $arr['coupon_start_time'];//优惠券开始时间
        $dataone['coupon_total_count'] = $arr['coupon_total_count'];//优惠券总量
        $dataone['item_url'] = $arr['item_url'];//商品地址
        $dataone['nick'] = $arr['nick'];//卖家昵称
        $dataone['num_iid'] = $arr['num_iid'];//商品ID
        $dataone['pict_url'] = $arr['pict_url'];//商品主图
        $dataone['provcity'] = $arr['provcity'] ? $arr['provcity'] : '';//宝贝所在地
        $dataone['reserve_price'] = $arr['reserve_price'];//商品一口价格

        $dataone['seller_id'] = $arr['seller_id'];//卖家id
        $dataone['shop_title'] = $arr['shop_title'];// 店铺名
        $dataone['small_images'] = $small_images ? $small_images : "{}";//小图
        $dataone['status'] = $arr['status'];  //必须为1，0失效，1有效
        $dataone['title'] = $arr['title'];//商品标题
        $dataone['commission_rate'] = $arr['tk_rate'];//收入比例,就是返利比例//leapmary特殊点
        //$dataone['category'] = $arr['type'];//1 普通商品； 2 鹊桥高佣金商品；3 定向招商商品；4 营销计划商品;
        $dataone['user_type'] = $arr['user_type'];//卖家类型，0表示集市，1表示商城
        $dataone['volume'] = $arr['volume'];//30天销量
        $dataone['zk_final_price'] = $arr['zk_final_price_wap'];//商品折扣价格
        $dataone['coupon_info'] = $arr['coupon_info']; //满99元减20元

        //$dataone['category'] = $arr['zk_final_price_wap'];//无线折扣价，即宝贝在无线上的实际售卖价格。
        $dataone['favorites_id'] = 0;

        $dataone['cate_id'] = 0;
        $dataone['two_cate_id'] = 0;

        if(empty($arr['coupon_info'])){
            $dataone['coupon_money'] = 0;
        }else{
            //leapmary//还有各种计算
            $v['coupon_info'] = str_replace('元减',',',$arr['coupon_info']);
            $v['coupon_info'] = str_replace('满','',$v['coupon_info']);
            $v['coupon_info'] = str_replace('元','',$v['coupon_info']);
            $v['coupon_info'] = explode(',',$v['coupon_info']);
            $dataone['coupon_money'] = $v['coupon_info'][1];                          //优惠卷面额
        }

        $dataone['coupon_price'] = $dataone['zk_final_price'] - $dataone['coupon_money'];          //卷后价

        //这个计算是错误的计算方式
        //$dataone['commission'] = ($dataone['zk_final_price'] * $dataone['commission_rate']) / 100;
        //正确的计算方式：卷后价*返利比例
        $dataone['commission'] = ($dataone['coupon_price'] * $dataone['commission_rate']) / 100;
        //leapmary//还有各种计算

        /*print_r($dataone);exit;*/

        return $dataone;

    }

}































