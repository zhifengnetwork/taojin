<?php

namespace app\admin\controller;

use clt\Form;
use think\Db;
use think\Request;

class Uatmfavorites extends Common
{
    protected $dao,$fields;

    public function _initialize(){
        parent::_initialize();
        $this->moduleid = $this->mod[MODULE_NAME];
        $this->dao = db(MODULE_NAME);
        $fields = F($this->moduleid . '_Field');

        foreach($fields as $key => $res){
            $res['setup'] = string2array($res['setup']);
            $this->fields[$key] = $res;
        }
        unset($fields);
        unset($res);
        $this->assign('fields',$this->fields);
    }

    /**
     *
     **/
    public function index(){

        if(request()->isPost()){
            $request = Request::instance();
            $modelname = strtolower($request->controller());
            $model = db($modelname);
            $keyword = input('post.key');
            $page = input('page') ? input('page') : 1;
            $pageSize = input('limit') ? input('limit') : config('pageSize');
            $order = "favorites_id desc";
            if(input('post.catid')){
                $catids = db('category')->where('parentid',input('post.catid'))->column('id');
                if($catids){
                    $catid = input('post.catid') . ',' . implode(',',$catids);
                }else{
                    $catid = input('post.catid');
                }
            }

            if(!empty($keyword)){
                $map['title'] = array('like','%' . $keyword . '%');
            }
            $prefix = config('database.prefix');
            $Fields = Db::getFields($prefix . $modelname);
            foreach($Fields as $k => $v){
                $field[$k] = $k;
            }
            if(in_array('catid',$field)){
                $map['catid'] = array('in',$catid);
            }
            $list = $model
                ->where($map)
                ->order($order)
                ->paginate(array('list_rows' => $pageSize,'page' => $page))
                ->toArray();
            //echo $model->getLastSql();
            $rsult['code'] = 0;
            $rsult['msg'] = "获取成功";
            $lists = $list['data'];
            foreach($lists as $k => $v){
                $lists[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
            }

            //leapmary//######################
            $classification = db('classification')->select();
            $classification = convert_arr_kv($classification,'id','title');
            foreach($lists as $k => $v){
                $lists[$k]['classification'] = $classification;
            }
            //leapmary//######################

            $rsult['data'] = $lists;
            $rsult['count'] = $list['total'];
            $rsult['rel'] = 1;

            return $rsult;
        }else{

            return $this->fetch('uatmfavorites/index');
        }
    }

    /**
     * 选择采集分类
     **/
    public function choosecate(){

        if(request()->isPost()){
            $cate_id = input('cate_id');
            $two_cate_id = input('two_cate_id');
            $favorites_id = input('favorites_id');

            $re = db('uatmfavorites')
                ->where('favorites_id',$favorites_id)
                ->update([
                    "cate_id" => $cate_id,
                    "two_cate_id" => $two_cate_id
                ]);

            print_r($re);
            exit;
        }

        $favorites_id = input('favorites_id');

        if(empty($favorites_id)){
            echo "系统错误";
            exit;
        }

        $this->assign('favorites_id',input('favorites_id'));

        return $this->fetch('uatmfavorites/choosecate');
    }

    public function edit(){
        $id = input('id');
        $request = Request::instance();
        $controllerName = $request->controller();
        if($controllerName == 'Page'){
            $p = $this->dao->where('id',$id)->find();
            if(empty($p)){
                $data['id'] = $id;
                $data['title'] = $this->categorys[$id]['catname'];
                $data['keywords'] = $this->categorys[$id]['keywords'];
                $this->dao->insert($data);
            }
        }
        $info = $this->dao->where('id',$id)->find();
        $form = new Form($info);
        $returnData['vo'] = $info;
        $returnData['form'] = $form;
        $this->assign('info',$info);
        $this->assign('form',$form);
        $this->assign('title','编辑内容');
        return $this->fetch('uatmfavorites/edit');
    }

    function update(){
        $request = Request::instance();

        $controllerName = $request->controller();
        $model = $this->dao;
        $fields = $this->fields;
        $data = $this->checkfield($fields,input('post.'));
        if($data['code'] == "0"){
            $result['msg'] = $data['msg'];
            $result['code'] = 0;
            return $result;
        }
        if(isset($fields['updatetime'])){
            $data['userid'] = session('aid');
        }

        if(isset($fields['updatetime'])){
            $data['updatetime'] = time();
        }

        $title_style = '';
        if(isset($data['style_color'])){
            $title_style .= 'color:' . $data['style_color'] . ';';
            unset($data['style_color']);
        }else{
            $title_style .= 'color:#222;';
        }
        if(isset($data['style_bold'])){
            $title_style .= 'font-weight:' . $data['style_bold'] . ';';
            unset($data['style_bold']);
        }else{
            $title_style .= 'font-weight:normal;';
        }
        if($fields['title']['setup']['style'] == 1){
            $data['title_style'] = $title_style;
        }
        if($controllerName != 'Page'){
            $data['updatetime'] = time();
        }
        unset($data['aid']);
        unset($data['pics_name']);
        //编辑多图和多文件
        foreach($fields as $k => $v){
            if($v['type'] == 'files' or $v['type'] == 'images'){
                if(!$data[$k]){
                    $data[$k] = '';
                }
                $data[$v['field']] = $data['images'];
            }
        }

        $list = $model->update($data);
        if(false !== $list){
            if($controllerName == 'Page'){
                $result['url'] = url("admin/category/index");
            }else{
                $result['url'] = url("admin/" . $controllerName . "/index",array('catid' => $data['catid']));
            }
            $result['msg'] = '修改成功!';
            $result['code'] = 1;
            return $result;
        }else{
            $result['msg'] = '修改失败!';
            $result['code'] = 0;
            return $result;
        }
    }

    public function set_categorys($categorys = array()){
        if(is_array($categorys) && !empty($categorys)){
            foreach($categorys as $id => $c){
                $this->categorys[$c['id']] = $c;
                $r = db('category')->where("parentid = $c[id]")->order('listorder ASC,id ASC')->select();
                $this->set_categorys($r);
            }
        }
        return true;
    }

    function checkfield($fields,$post){
        foreach($post as $key => $val){
            if(isset($fields[$key])){
                $setup = $fields[$key]['setup'];
                if(!empty($fields[$key]['required']) && empty($post[$key])){
                    $result['msg'] = $fields[$key]['errormsg'] ? $fields[$key]['errormsg'] : '缺少必要参数！';
                    $result['code'] = 0;
                    return $result;
                }
                if(isset($setup['multiple'])){
                    if(is_array($post[$key])){
                        $post[$key] = implode(',',$post[$key]);
                    }
                }
                if(isset($setup['inputtype'])){
                    if($setup['inputtype'] == 'checkbox'){
                        $post[$key] = implode(',',$post[$key]);
                    }
                }
                if(isset($setup['fieldtype'])){
                    if($fields[$key]['type'] == 'checkbox'){
                        $post[$key] = implode(',',$post[$key]);
                    }
                }
                if($fields[$key]['type'] == 'datetime'){
                    $post[$key] = strtotime($post[$key]);
                }elseif($fields[$key]['type'] == 'textarea'){
                    $post[$key] = addslashes($post[$key]);
                }elseif($fields[$key]['type'] == 'linkage'){
                    if($post[$key][0]){
                        $post[$key] = implode(',',$post[$key]);
                    }else{
                        unset($post[$key]);
                    }
                }elseif($fields[$key]['type'] == 'editor'){
                    if(isset($post['add_description']) && $post['description'] == '' && isset($post['content'])){
                        $content = stripslashes($post['content']);
                        $description_length = intval($post['description_length']);
                        $post['description'] = str_cut(str_replace(array("\r\n","\t",'[page]','[/page]','&ldquo;','&rdquo;'),'',strip_tags($content)),$description_length);
                        $post['description'] = addslashes($post['description']);
                    }
                    if(isset($post['auto_thumb']) && $post['thumb'] == '' && isset($post['content'])){
                        $content = $content ? $content : stripslashes($post['content']);
                        $auto_thumb_no = intval($post['auto_thumb_no']) * 3;
                        if(preg_match_all("/(src)=([\"|']?)([^ \"'>]+\.(gif|jpg|jpeg|bmp|png))\\2/i",$content,$matches)){
                            $post['thumb'] = $matches[$auto_thumb_no][0];
                        }
                    }
                }
            }
        }
        return $post;
    }

    public function add(){
        $form = new Form();
        $this->assign('form',$form);
        $this->assign('title','添加内容');
        return $this->fetch('uatmfavorites/edit');
    }

    public function insert(){
        $request = Request::instance();
        $controllerName = $request->controller();
        $model = $this->dao;
        $fields = $this->fields;
        $data = $this->checkfield($fields,input('post.'));
        if(isset($data['code']) && $data['code'] == 0){
            return $data;
        }
        if($fields['createtime'] && empty($data['createtime'])){
            $data['createtime'] = time();
        }
        if($fields['updatetime'] && empty($data['updatetime'])){
            $data['updatetime'] = time();
        }
        if($controllerName != 'Page'){
            if($fields['updatetime']){
                $data['updatetime'] = $data['createtime'];
            }
        }
        $data['userid'] = session('aid');
        $data['username'] = session('username');

        $title_style = '';
        if(isset($data['style_color'])){
            $title_style .= 'color:' . $data['style_color'] . ';';
            unset($data['style_color']);
        }else{
            $title_style .= 'color:#222;';
        }
        if(isset($data['style_bold'])){
            $title_style .= 'font-weight:' . $data['style_bold'] . ';';
            unset($data['style_bold']);
        }else{
            $title_style .= 'font-weight:normal;';
        }
        if($fields['title']['setup']['style'] == 1){
            $data['title_style'] = $title_style;
        }

        $aid = $data['aid'];
        unset($data['style_color']);
        unset($data['style_bold']);
        unset($data['aid']);
        unset($data['pics_name']);
        //编辑多图和多文件
        foreach($fields as $k => $v){
            if($v['type'] == 'files' or $v['type'] == 'images'){
                if(!$data[$k]){
                    $data[$k] = '';
                }
                $data[$v['field']] = $data['images'];
            }
        }
        $id = $model->insertGetId($data);
        if($id !== false){
            $catid = $controllerName == 'page' ? $id : $data['catid'];

            if($aid){
                $Attachment = db('attachment');
                $aids = implode(',',$aid);
                $data2['id'] = $id;
                $data2['catid'] = $catid;
                $data2['status'] = '1';
                $Attachment->where("aid in (" . $aids . ")")->update($data2);
            }
            if($controllerName == 'page'){
                $result['url'] = url("admin/category/index");
            }else{
                $result['url'] = url("admin/" . $controllerName . "/index",array('catid' => $data['catid']));
            }
            $result['msg'] = '添加成功!';
            $result['code'] = 1;
            return $result;
        }else{
            $result['msg'] = '添加失败!';
            $result['code'] = 0;
            return $result;
        }

    }


    public function listDel(){
        $id = input('post.id');
        $model = $this->dao;
        $model->where(array('id' => $id))->delete();//转入回收站
        return ['code' => 1,'msg' => '删除成功！'];
    }

    public function delAll(){
        $map['id'] = array('in',input('param.ids/a'));
        $model = $this->dao;
        $model->where($map)->delete();
        $result['code'] = 1;
        $result['msg'] = '删除成功！';
        $result['url'] = url('index',array('catid' => input('post.catid')));
        return $result;
    }

    public function listorder(){
        $model = $this->dao;
        $catid = input('catid');
        $data = input('post.');
        $model->update($data);
        $result = ['msg' => '排序成功！','url' => url('index',array('catid' => $catid)),'code' => 1];
        return $result;
    }

    public function delImg(){
        if(!input('post.url')){
            return ['code' => 0,'请指定要删除的图片资源'];
        }
        $file = ROOT_PATH . __PUBLIC__ . input('post.url');
        if(file_exists($file) && trim(input('post.url')) != ''){
            is_dir($file) ? dir_delete($file) : unlink($file);
        }
        if(input('post.id')){
            $picurl = input('post.picurl');
            $picurlArr = explode(':',$picurl);
            $pics = substr(implode(":::",$picurlArr),0,-3);
            $model = $this->dao;
            $map['id'] = input('post.id');
            $model->where($map)->update(array('pics' => $pics));
        }
        $result['msg'] = '删除成功!';
        $result['code'] = 1;
        return $result;
    }

    public function getRegion(){
        $Region = db("region");
        $map['pid'] = input("pid");
        $list = $Region->where($map)->select();
        return $list;
    }

    /**
     * 同步选品库类目
     **/
    public function uatmfavoritesget(){

        $config = db('config')->where("inc_type = 'taobaoke'")->select();
        $info = convert_arr_kv($config,'name','value');

        $appkey = $info['appkey'];
        $secret = $info['secretkey'];
        $sessionKey = $info['sessionkey'];

        $resp = require_once("./vendor/taobaokeapi/uatmfavoritesget.php");

        $arr = json_decode(json_encode($resp),true);
        $arr = $arr['results']['tbk_favorites'];

        if(!empty($arr)){
            db('uatmfavorites')->where("listorder",0)->delete();

            if(count($arr[0]) <= 1){
                $re = db('uatmfavorites')->insertGetId($arr);
            }else{
                $re = db('uatmfavorites')->insertAll($arr);
            }

            print_r($re);
        }else{
            print_r(0);
        }


    }

    /**
     * 采集选品库
     **/
    public function uatmfavorites_getgoods(){

        $favorites_id = input('favorites_id');

        //获取配置栏目id
        $uatmfavorites = db('uatmfavorites')->where("favorites_id",$favorites_id)->find();

        $config = db('config')->where("inc_type = 'taobaoke'")->select();
        $info = convert_arr_kv($config,'name','value');

        $appkey = $info['appkey'];
        $secret = $info['secretkey'];
        $sessionKey = $info['sessionkey'];

        $extensionbit = action('common/Alluse/get_extensionbit',['id' => '1']);

        $resp = require_once("./vendor/taobaokeapi/uatmfavorites_getgoods.php");

        $arr = json_decode(json_encode($resp),true);

        $arr = $arr['results']['uatm_tbk_item'];

        $dataone = [];

        // print_r($arr);exit;

        if(empty($arr[0])){
            //只有一个商品
            if(!empty($arr)){
                //所需要的所有信息，都整理了getonetbkgoods
                $dataone = $this->getonetbkgoods($arr,$favorites_id,$uatmfavorites);
                $arrdataall[0] = $dataone;
            }
        }else{
            //多个商品
            $arrdataall = [];
            foreach($arr as $k => $v){
                $arrdataall[] = $this->getonetbkgoods($v,$favorites_id,$uatmfavorites);
            }
        }

        //leapmary//统一处理//#######
        //leapmary//全商品存储//#######
        $respdelarr = convert_arr_k_all($arrdataall,'num_iid');
        db('goods')->where('num_iid','in',$respdelarr)->delete();
        db('goods_all')->where('num_iid','in',$respdelarr)->delete();
        db('goods')->where("favorites_id",$favorites_id)->delete();

        db('goods_all')->insertAll($arrdataall);
        $re = db('goods')->insertAll($arrdataall);

        $arrdataallnew = [];
        foreach($arrdataall as $k => $v){
            //营销链接不为空的才能转换淘口令
            if(!empty($v['coupon_click_url'])){
                $arrdataallnew[] = $v;
            }
        }

        $goodstbk = $arrdataallnew;
        require_once("./vendor/taobaokeapi/tpwdcreate.php");
        print_r($re);
        exit;

    }

    public function getonetbkgoods($arr,$favorites_id,$uatmfavorites){

        $dataone = [];
        $small_images = json_encode($arr['small_images']);
        $dataone['createtime'] = date("Y-m-d h:i:s");
        $dataone['category'] = $arr['category'];    //后台一级类目
        $dataone['coupon_click_url'] = $arr['coupon_click_url']; //商品优惠券推广链接
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
        $dataone['favorites_id'] = $favorites_id;

        $dataone['cate_id'] = $uatmfavorites['cate_id'];
        $dataone['two_cate_id'] = $uatmfavorites['two_cate_id'];

        //leapmary//还有各种计算
        $v['coupon_info'] = str_replace('元减',',',$arr['coupon_info']);
        $v['coupon_info'] = str_replace('满','',$v['coupon_info']);
        $v['coupon_info'] = str_replace('元','',$v['coupon_info']);
        $v['coupon_info'] = explode(',',$v['coupon_info']);

        $dataone['coupon_money'] = $v['coupon_info'][1];                          //优惠卷面额
        $dataone['coupon_price'] = $dataone['zk_final_price'] - $dataone['coupon_money'];          //卷后价

        //这个计算是错误的计算方式 四舍五入round($a,2)
        $dataone['commission'] = ($dataone['zk_final_price'] * $dataone['commission_rate']) / 100*0.6;
        $dataone['commission'] = round($dataone['commission'],2);
        //正确的计算方式：卷后价*返利比例
        //$dataone['commission'] = ($dataone['coupon_price'] * $dataone['commission_rate']) / 100;
        //leapmary//还有各种计算

        return $dataone;

    }

}