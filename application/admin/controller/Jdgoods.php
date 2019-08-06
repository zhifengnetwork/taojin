<?php

namespace app\admin\controller;

use clt\Form;
use think\Db;
use think\Request;

class Jdgoods extends Common
{
    protected $dao,$fields;

    public function _initialize(){

        parent::_initialize();
        $this->moduleid = $this->mod[strtolower(MODULE_NAME)];

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
            foreach($lists as $k => $v){
                $lists[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
            }
            $rsult['data'] = $lists;
            $rsult['count'] = $list['total'];
            $rsult['rel'] = 1;
            return $rsult;
        }else{
            return $this->fetch('jdgoods/index');
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
        $form = new Form($info);
        $returnData['vo'] = $info;
        $returnData['form'] = $form;
        $this->assign('info',$info);
        $this->assign('form',$form);
        $this->assign('title','编辑内容');
        return $this->fetch('jdgoods/edit');
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

        //卷后价
        $data['coupon_price'] = $data['wlUnitPrice']-$data['discount'];

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
        return $this->fetch('jdgoods/edit');
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
        if(file_exists($file) && tridb(input('post.url')) != ''){
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
     * 获取京东推广商品信息
     **/
    public function getjdapigoodinfo(){

        //$_POST['url'] = "http://item.jd.com/5901356.html";

        $urlinfo = explode("item.jd.com/",$_POST['url']);

        $skuId = str_replace(".html","",$urlinfo[1]);

        //需要转链产品的地址
        $sourceurl = $_POST['url'];

        //自定义链接转换
        $method = "jingdong.service.promotion.getcode";
        $channel = "PC";
        $type = 7;

        //获取京东配置信息
        $confing = action('common/Alluse/getconfig',['inc_type'=>'jingdongke']);

        //leapmary//#####################################
        $unionId = $confing['unionid'];
        //$webId = "您在京东联盟注册的网站的ID";
        $webId = $confing['webid'];
        $token = $confing['token'];
        $appkey = $confing['appkey'];
        $appSecret = $confing['appsecret'];
        //leapmary//#####################################

        $v = "2.0";
        $time = date('Y-m-d H:i:s',time());

        $baseurl = "https://api.jd.com/routerjson?";

        //应用参数，json格式
        $_360buy_param_json =
            '{"channel":"' . $channel . '","materialId":"' . $sourceurl . '","promotionType":' . $type . ',"unionId":"' . $unionId . '",
  "webId":"' . $webId . '"}';

        //系统参数
        $fields = [
            "360buy_param_json" => urlencode($_360buy_param_json),
            "access_token" => urlencode($token),
            "app_key" => urlencode($appkey),
            "method" => urlencode($method),
            "timestamp" => urlencode($time),
            "v" => urlencode($v)
        ];

        $fields_string = "";

        //用来计算md5，以appSecret开头
        $_tempString = $appSecret;

        foreach($fields as $key => $value){
            //直接将参数和值拼在一起
            $_tempString .= $key . $value;
            //作为url参数的字符串
            $fields_string .= $key . '=' . $value . '&';
        }

        //最后再拼上appSecret
        $_tempString .= $appSecret;

        //计算md5，然后转为大写，sign参数作为url中的最后一个参数
        $sign = strtoupper(md5($_tempString));

        //加到最后
        $fields_string .= ("sign=" . $sign);

        //最终请求的url
        $link = $baseurl . $fields_string;

        //发送get请求
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$link);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        $result = curl_exec($ch);
        curl_close($ch);

        //转换为json
        $jsonArray = json_decode($result,true);
        $jdinfo = json_decode($jsonArray['jingdong_service_promotion_getcode_responce']['queryjs_result'],true);
        //$jdinfo['url']这个是转链的链接

        $data['tkurl'] = $jdinfo['url'];
        /*##转链#############################################################*/
        //需要转链产品的地址
        $method = "jingdong.service.promotion.goodsInfo";

//应用参数，json格式
        $_360buy_param_json =
            '{"channel":"' . $channel . '","materialId":"' . $sourceurl . '","promotionType":' . $type . ',"unionId":"' . $unionId . '",
  "webId":"' . $webId . '","skuIds":"' . $skuId . '"}';

//系统参数
        $fields = [
            "360buy_param_json" => urlencode($_360buy_param_json),
            "access_token" => urlencode($token),
            "app_key" => urlencode($appkey),
            "method" => urlencode($method),
            "timestamp" => urlencode($time),
            "v" => urlencode($v)
        ];

        $fields_string = "";

        //用来计算md5，以appSecret开头
        $_tempString = $appSecret;

        foreach($fields as $key => $value){
            //直接将参数和值拼在一起
            $_tempString .= $key . $value;
            //作为url参数的字符串
            $fields_string .= $key . '=' . $value . '&';
        }

        //最后再拼上appSecret
        $_tempString .= $appSecret;

        //计算md5，然后转为大写，sign参数作为url中的最后一个参数
        $sign = strtoupper(md5($_tempString));

        //加到最后
        $fields_string .= ("sign=" . $sign);

        //最终请求的url
        $link = $baseurl . $fields_string;

        //发送get请求
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$link);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        $result = curl_exec($ch);
        curl_close($ch);

        /*print_r($result);exit;*/

        //转换为json
        $jsonArray = json_decode($result,true);

        $dataarr = json_decode($jsonArray['jingdong_service_promotion_goodsInfo_responce']['getpromotioninfo_result'],true);
        $data['goodsinfo'] = $dataarr['result'][0];
        $goodsinfo = $data['goodsinfo'];
        /*##商品信息#############################################################*/

        $xuanku_info = db('jdgoods')->where('skuId',$goodsinfo['skuId'])->find();
        /*print_r(db('jdgoods')->getLastSql());exit;*/
        $goodsinfo['tkurl'] = $data['tkurl'];

        if(!$xuanku_info){
      
            //$datainfo['good_id'] = db('jdgoods')->insertGetId($goodsinfo);
            //$data['good_id'] = $datainfo['good_id'];

        }else{
            db('jdgoods')
                ->where("id = " . $xuanku_info['id'])
                ->update($goodsinfo);
            $data['jdgoods'] = $xuanku_info;
        }

        print_r(json_encode($data));
        exit;
    }
}