<?php

namespace app\admin\controller;

use clt\Form;
use think\Db;
use think\Request;

class Goods extends Common
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

    public function index(){

        if(request()->isPost()){

            $request = Request::instance();
            $modelname = strtolower($request->controller());
            $model = db($modelname);
            $keyword = input('post.key');

            /*print_r($keyword);exit;*/

            $page = input('page') ? input('page') : 1;
            $pageSize = input('limit') ? input('limit') : config('pageSize');
            $order = "listorder asc,id desc";
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

            $platformname[1] = "PC";
            $platformname[2] = "无线";

            //分类信息
            $classification = db('classification')->select();
            $classification = convert_arr_kv($classification,'id','title');

            foreach($lists as $k => $v){
                $lists[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
                $lists[$k]['platform'] = $platformname[$v['platform']];
                $lists[$k]['cate_id'] = $classification[$v['cate_id']] ? $classification[$v['cate_id']] : "";
                $lists[$k]['two_cate_id'] = $classification[$v['two_cate_id']] ? $classification[$v['two_cate_id']] : "";
            }
            $rsult['data'] = $lists;
            $rsult['count'] = $list['total'];
            $rsult['rel'] = 1;

            return $rsult;

        }else{
            return $this->fetch('goods/index');
        }
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
        $info['createtime'] = strtotime($info['createtime']);

        /*print_r($info);exit;*/
        $form = new Form($info);
        $returnData['vo'] = $info;
        $returnData['form'] = $form;

        $this->assign('info',$info);
        $this->assign('form',$form);
        $this->assign('title','编辑内容');
        return $this->fetch('goods/edit');
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

        $data['createtime'] = date("Y-m-d h:i:s");
        $data['updatetime'] = date("Y-m-d h:i:s");

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

        //leapmary//进行一些矫正

        $list = $model->update($data);

        //是否获取淘口令
        if($data['tpwd'] == '' && $data['coupon_click_url'] != ''){

            $newdata[0] = $data;
            $goodstbk = $newdata;

            /*##就这么玩#################################*/
            $config = db('config')->where("inc_type = 'taobaoke'")->select();
            $info = convert_arr_kv($config,'name','value');

            $appkey = $info['appkey'];
            $secret = $info['secretkey'];
            $sessionKey = $info['sessionkey'];

            require_once("./vendor/taobaokeapi/tpwdcreate.php");
        }

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
        $this->assign('title','添加商品');
        return $this->fetch('goods/edit');
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

        $data['createtime'] = date("Y-m-d h:i:s");
        $data['updatetime'] = date("Y-m-d h:i:s");

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

        /*print_r($data);exit;*/
        //是否更新
        $goodinfo = $model->where("num_iid",$data['num_iid'])->find();

        if(!empty($goodinfo)){
            //总商品表插入一条
            db('goods_all')->where("num_iid",$data['num_iid'])->update($data);
            $id = $model->where("num_iid",$data['num_iid'])->update($data);

            $result['msg'] = '商品已经存在，更新成功!';
            $result['code'] = 0;
            return $result;
        }else{
            //总商品表插入一条
            db('goods_all')->insertGetId($data);
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


    }

    public function listDel(){
        $id = input('post.id');
        $model = $this->dao;
        $model->where(array('id' => $id))->delete();//转入回收站
        return ['code' => 1,'msg' => '删除成功！'];
    }

    //添加上下架功能
    //修改数据库status的值
    public function listXiajia(){
        $id = input('post.id');
        $ststus = input('post.status');
        $model = $this->dao;

        if($ststus == 1){
            $model->where("id = " . $id)->update([
                "status" => 0
            ]);
            return ['code' => 1,'msg' => '上架成功！'];
        }else{
            $model->where("id = " . $id)->update([
                "status" => 1
            ]);
            return ['code' => 1,'msg' => '下架成功！'];
        }


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

    //执行好券清单采集
    public function coupongetdo(){

        $id = input('id');
        $couponget = db('couponget')->where("id = $id")->find();

        $config = db('config')->where("inc_type = 'taobaoke'")->select();
        $info = convert_arr_kv($config,'name','value');

        $appkey = $info['appkey'];
        $secret = $info['secretkey'];
        $sessionKey = $info['sessionkey'];

        $resp = require_once("./vendor/taobaokeapi/tbk.php");

        if(!empty($resp)){
            foreach($resp as $k => $v){
                $resp[$k]['small_images'] = json_encode($v['small_images']);
            }

            $respdelarr = convert_arr_k_all($resp,'num_iid');
            db('goods')->where('num_iid','in',$respdelarr)->delete();

            $re = db('goods')->insertAll($resp);
        }

        print_r($re);

    }

    //执行单个采集商品
    public function gettaobaoapigoodinfo(){
        //先进行插件采集数据
        $goodurl = urlencode($_POST['url']);

        /*$goodurl = urlencode('https://item.taobao.com/item.htm?spm=a219t.7900221/10.1998910419.d30ccd691.279f75a5UpAQSy&id=567973056473');*/

        $config = db('config')->where("inc_type = 'taobaoke'")->select();
        $info = convert_arr_kv($config,'name','value');

        $server = $info['server_c'];
        $key = $info['key_c'];
        $port = $info['port_c'];

        $extensionbit = db('extensionbit')->where("id",1)->find();

        $pid = $extensionbit['adzonepid'];

        // 根据商品链接获取转链后的数据
        $send_data = "api?Key=" . $key . "&getname=msg&content=" . $goodurl . "&pid=" . $pid;

        $url = $server . ':' . $port . '/' . $send_data;

        /*print_r($url);exit;*/

        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch,CURLOPT_TIMEOUT,15);//超时时间,自行按情况而定
        $output = curl_exec($ch);
        curl_close($ch);

        $output = json_decode($output,true);

        $goods_info = $output[0]['data']['pageList'][0];
        $paginator = $output[0]['data']['paginator'];
        $taobaoshare = $output[1]['data'];

        //插件是否能获取到商品，获取不到就用淘宝接口获取

        //判断优惠卷
        if($goods_info['couponStartFee'] <= $goods_info['zkPrice']){
            $goods_info['coupon_price'] = $goods_info['zkPrice'] - $goods_info['couponAmount'];
        }

        $datainfo['goods_info'] = $goods_info;
        $datainfo['paginator'] = $paginator;
        $datainfo['taobaoshare'] = $taobaoshare;

        if(!$output[0]['data']['pageList'][0]){
            response('400','商品数据不存在');
        }

        $xuanku_info = db('goods')->where('num_iid',$goods_info['auctionId'])->find();

        //print_r($goods_info);exit;

        if(!empty($xuanku_info)){
            print_r(0);
            exit;
        }

        $data = [
            'cate_id' => $goods_info['cate_id'],
            'two_cate_id' => $goods_info['two_cate_id'],
            'num_iid' => $goods_info['auctionId'],
            'title' => $goods_info['title'],
            'pict_url' => 'http:' . $goods_info['pictUrl'],
            'shop_title' => $goods_info['shopTitle'],
            'price' => $goods_info['zkPrice'],
            'month_sales' => $goods_info['biz30day'],
            'active_inc_scale' => $goods_info["eventRate"] ? $goods_info["eventRate"] : '0.00',
            'coupon_surplus' => $goods_info['couponLeftCount'],
            'couponAmount' => $goods_info['couponAmount'],
            'coupon_money' => $goods_info['couponAmount'],
            'coupon_price' => $goods_info['zkPrice'] - $goods_info['couponAmount'],
            'add_time' => date('Y-m-d H:i:s'),
            'reserve_price' => $goods_info['zkPrice'],
            'user_type' => $goods_info['userType'],
            'tk_rate' => $goods_info["tkRate"],
            'my_type' => $goods_info["my_type"],
            'status' => 0
        ];

        $datainfo['xuanku_info'] = $data;
        $pluginfo = $datainfo;

        //采用淘宝接口获取商品信息，只能获取商品的基本信息，没有返利和推广信息
        $config = db('config')->where("inc_type = 'taobaoke'")->select();
        $info = convert_arr_kv($config,'name','value');

        $appkey = $info['appkey'];
        $secret = $info['secretkey'];
        $sessionKey = $info['sessionkey'];
        $re = $this->parse_url_param($_POST['url']);
        $num_iids = $re['id'];

        $resp = require_once("./vendor/taobaokeapi/infoget.php");

        $arr = json_decode(json_encode($resp),true);

        $arr['results']['n_tbk_item']['small_images'] = json_encode($arr['results']['n_tbk_item']['small_images']);

        $lastdata = [];
        $lastdata['pluginfo'] = $pluginfo;
        $lastdata['tbkapidata'] = $arr['results']['n_tbk_item'];

        print_r(json_encode($lastdata));


    }

    /**
     * 置顶商品
     */
    public function set_top(){

        $id = input('id');

        $xuanku_goods = db('goods')->order('sort desc')->find();
        $xuanku_goodsself = db('goods')->where("id = " . $id)->find();

        if($xuanku_goodsself['sort'] == 0 || $xuanku_goodsself['sort'] == ''){
            $re = db('goods')->where("id = " . $id)->update([
                "sort" => $xuanku_goods['sort'] + 1
            ]);
        }else{
            $re = db('goods')->where("id = " . $id)->update([
                "sort" => 0
            ]);
        }

        echo $re;

    }

    /**
     * 获取url中的各个参数
     * 类似于 pay_code=alipay&bank_code=ICBC-DEBIT
     * @param type $str
     * @return type
     */
    public function parse_url_param($str){
        $data = array();
        $arr = array();
        $p = array();
        $arr = explode('?',$str);
        $p = explode('&',$arr[1]);
        foreach($p as $val){
            $tmp = explode('=',$val);
            $data[$tmp[0]] = $tmp[1];
        }
        return $data;
    }

}