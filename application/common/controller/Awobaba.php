<?php

namespace app\common\controller;

//db()用省略表前缀名Db::table()用表全名
use think\Controller;

class Awobaba extends Controller
{
    public function _initialize(){
        $config = db('config')->where("inc_type = 'taobaoke'")->select();
        $info = convert_arr_kv($config,'name','value');

        $this->server = $info['server_c'];
        $this->key = $info['key_c'];
        $this->port = $info['port_c'];
    }

    /*
     * (用商品ID获取用户信息)
     */
    public function get_good_info_by_id($goods_id){

        //获取推广位
        $extensionbit = db('extensionbit')->where('id',1)->find();
        $pid = $extensionbit['adzonepid'];

        // 通过商品ID获取商品数据
        $send_data = "api?key=$this->key&utf=1&getname=link&id=$goods_id&pid=$pid";
        $info = curl_alimama($this->server . ':' . $this->port . '/' . $send_data);
        /*print_r($this->server . ':' . $this->port . '/' . $send_data);exit;*/
        return $info;
    }

    /**
     * 插件产品信息录入数据库
     **/
    public function good_insert_table($data,$selfrebate = 100){
        $selfrebate = 100;
        //插件信息
        //console.log(pluginfo);
        $goodinfo['status'] = 0;
        $goodinfo['createtime'] = date("Y-m-d h:i:s");
        $goodinfo['updatetime'] = date("Y-m-d h:i:s");
        $goodinfo['shop_title'] = $data[0]['data']['pageList'][0]['shopTitle'];
        $goodinfo['user_type'] = $data[0]['data']['pageList'][0]['userType'];
        $goodinfo['zk_final_price'] = $data[0]['data']['pageList'][0]['zkPrice'];
        $goodinfo['title'] = $data[0]['data']['pageList'][0]['title'];
        $goodinfo['nick'] = $data[0]['data']['pageList'][0]['nick'];
        $goodinfo['seller_id'] = $data[0]['data']['pageList'][0]['sellerId'];
        $goodinfo['volume'] = $data[0]['data']['pageList'][0]['biz30day'];
        $goodinfo['pict_url'] = $data[0]['data']['pageList'][0]['pictUrl'];
        $goodinfo['item_url'] = $data[0]['data']['pageList'][0]['auctionUrl'];

        $goodinfo['coupon_total_count'] = $data[0]['data']['pageList'][0]['couponTotalCount']; //
        $goodinfo['coupon_remain_count'] = $data[0]['data']['pageList'][0]['couponLeftCount']; //
        $goodinfo['commission_rate'] = $data[0]['data']['pageList'][0]['tkRate'];//
        $goodinfo['coupon_money'] = $data[0]['data']['pageList'][0]['couponAmount'];//
        $goodinfo['coupon_info'] = $data[0]['data']['pageList'][0]['couponInfo'];//
        $goodinfo['category'] = ''; //
        $goodinfo['num_iid'] = $data[0]['data']['pageList'][0]['auctionId'];//
        $goodinfo['coupon_start_time'] = $data[0]['data']['pageList'][0]['couponEffectiveStartTime'];//
        $goodinfo['coupon_end_time'] = $data[0]['data']['pageList'][0]['couponEffectiveEndTime'];//

        if(empty($data[1]['data']['couponShortLinkUrl'])){
            $coupon_click_url = $data[1]['data']['shortLinkUrl'];
        }else{
            $coupon_click_url = $data[1]['data']['couponShortLinkUrl'];
        }

        if(empty($data[1]['data']['couponLinkTaoToken'])){
            $tpwd = $data[1]['data']['taoToken'];
        }else{
            $tpwd = $data[1]['data']['couponLinkTaoToken'];
        }

        $goodinfo['coupon_click_url'] = $coupon_click_url; //
        $goodinfo['item_description'] = $data[0]['data']['pageList'][0]['title']; //
        $goodinfo['small_images'] = ''; //
        $goodinfo['tpwd'] = $tpwd; //
        $goodinfo['cate_id'] = 6;  //后台插件添加
        $goodinfo['two_cate_id'] = 6; //后台插件添加

        $coupon_price = $goodinfo['zk_final_price'] - $goodinfo['coupon_money'];
        if($coupon_price < 0){
            $coupon_price = 0;
        }

        $goodinfo['coupon_price'] = $coupon_price; //
        $goodinfo['commission'] = get_fanli($goodinfo['zk_final_price'],$goodinfo['coupon_money'],$goodinfo['commission_rate'],$selfrebate,$data[0]['data']['pageList'][0]['tkCommonFee']);

        $re = db('goods')->insertGetId($goodinfo);
        db('goods_all')->insertGetId($goodinfo);

        return $goodinfo;
    }


}







