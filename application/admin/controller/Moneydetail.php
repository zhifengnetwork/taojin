<?php

namespace app\admin\controller;

use clt\Form;
use think\Db;
use think\Request;

class Moneydetail extends Common
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
     * 这里请求数据条数
     **/
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
            $map=[];
            //如果是提现管理，只显示提现
            if(!empty($_POST['type'])){
                $map['type'] = $_POST['type'];
            }

            /*#########################################*/
            $id = input('post.id');
            if(!empty($id)){
                $map['user_id'] = array('eq',$id);
            }
            /*#########################################*/

            if(!empty($keyword)){
                $map['user_id|id'] = array('like','%' . $keyword . '%');
            }
            $prefix = config('database.prefix');
            $Fields = Db::getFields($prefix . $modelname);
            foreach($Fields as $k => $v){
                $field[$k] = $k;
            }
            $list = $model
            ->where($map)
                ->order($order)
                ->paginate(array('list_rows' => $pageSize,'page' => $page))
                ->toArray();
//            $list = Db::name('moneydetail')->alias('m')
//                ->join('users u','u.id=m.user_id','LEFT')
//                ->field('m.*,u.phone,u.nick_name,u.ali_account,u.name')
//                ->where($map)
//                ->order($order)
//                ->paginate(array('list_rows' => $pageSize,'page' => $page))
//                ->toArray();

//            $list = $model->query("SELECT
//	clt_moneydetail.*,clt_users.phone,clt_users.nick_name,clt_users.ali_account,clt_users.name
//FROM
//	clt_moneydetail,
//	clt_users
//WHERE
//	clt_moneydetail.user_id = clt_users.id
//	".(!empty($id)?" AND clt_moneydetail.user_id={$id} ":'')."
//ORDER BY
//	clt_moneydetail.listorder ASC,
//	clt_moneydetail.id DESC LIMIT ".($page - 1)*$pageSize.",".$pageSize);
            $rsult['code'] = 0;
            $rsult['msg'] = "获取成功";

            $rsult['data'] = $list['data'];
            $rsult['count'] = $list['total'];
            $rsult['rel'] = 1;
            return $rsult;

        }else{

            $id = input('id');
            $this->assign("id",$id);

            return $this->fetch('moneydetail/index');
        }
    }

    /**
     * 提现申请
     **/
    public function voucher(){

        if(request()->isPost()){

            //更改审核状态
            if(empty($_POST['id'])){
                echo "系统异常";
                exit;
            }

            //更改订单状态
            $moneydetail = db('moneydetail')->where('id',$_POST['id'])->update([
                'v_state'=> 1
            ]);

            echo $moneydetail;
            exit;
        }

        return $this->fetch('moneydetail/voucher');

    }

    //修改数据库status的值
    public function listXiajia(){
        $id = input('post.id');
        $v_state = input('post.status');
        //如果等于0，则更改状态为1
        Db::startTrans();
        if($v_state==0){
            $result=db('withdraw')
                ->where('id',$id)
                ->update(['status'=> 1
                ]);
            if(!$result){
                Db::rollback();
                return ['code' => 2,'msg' => '更新失败！'];
            }
            $system_money=Db::name('system_money')->where('id',1)->find();
            $withdraw=db('withdraw')->where('id',$id)->find();
            $system_money['balance']=sprintf("%.2f",$system_money['balance']+$withdraw['money']);
            $system_data['balance']=$withdraw['money'];
            $system_data['new_balance']=$system_money['balance'];
            $system_data['add_time']=time();
            $system_data['desc']='提现审核修改系统金额';
            $sys_id=Db::name('system_money_log')->insertGetId($system_data);
            if(!$sys_id){
                Db::rollback();
                return ['status' => -2, 'msg' => '审核失败,生成系统log出错！'];
            }
            $rs=Db::name('system_money')->update($system_money);//修改
            if(!$rs){
                Db::rollback();
                return ['status' => -2, 'msg' => '系统修改余额失败，审核失败！'];
            }
        }
        Db::commit();
        return ['code' => 1,'msg' => '审核成功！'];

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
        /*$info['coupon_start_time'] = strtotime($info['coupon_start_time']);
        $info['coupon_end_time'] = strtotime($info['coupon_end_time']);*/

        $form = new Form($info);
        $returnData['vo'] = $info;
        $returnData['form'] = $form;

        $this->assign('info',$info);
        $this->assign('form',$form);
        $this->assign('title','编辑内容');
        return $this->fetch('moneydetail/edit');
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
        $this->assign('title','添加商品');
        return $this->fetch('moneydetail/edit');
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

    public function withdraw(){
        if(request()->isPost()){
            $keyword = input('post.key');
            $page = input('page') ? input('page') : 1;
            $pageSize = input('limit') ? input('limit') : config('pageSize');

            $id = input('post.id');
            if(!empty($id)){
                $map['user_id'] = array('eq',$id);
            }

            if(!empty($keyword)){
                $map['title'] = array('like','%' . $keyword . '%');
            }
            $list = Db::name('withdraw')->alias('w')
                ->join('users u','u.id=w.user_id','')
                ->field('u.name,u.realname,w.*')
                ->order('w.id desc')
                ->paginate(array('list_rows' => $pageSize,'page' => $page))
                ->toArray();
            foreach ($list['data'] as &$v){
                if($v['type']==2){
                    $card = Db::name('card')->where(['id'=>intval($v['info']),'user_id'=>$v['user_id']])->field('name,bank,number')->find();
                    $card&&$v['info'] =implode($card,' ');
                }
            }
            return [
                'code' => 0,
                'msg' => "获取成功",
                'data' =>$list['data'],
                'count' => $list['total'],
                'rel' => 1
            ];

        }
    }

    public function bi(){

        if(request()->isPost()){
            $keyword = input('post.key');
            $page = input('page') ? input('page') : 1;
            $pageSize = input('limit') ? input('limit') : config('pageSize');

            if(!empty($keyword)){
                $map['id|user_id|user_name'] = array('like','%' . $keyword . '%');
            }

            $list = Db::name('users_currency')
                ->where($map)
                ->order('id desc')
                ->paginate(array('list_rows' => $pageSize,'page' => $page))
                ->toArray();

            //echo $model->getLastSql();
            $rsult['code'] = 0;
            $rsult['msg'] = "获取成功";
            $lists = $list['data'];

            $rsult['data'] = $lists;
            $rsult['count'] = $list['total'];
            $rsult['rel'] = 1;
            return $rsult;
        }else{
            return $this->fetch('moneydetail/bi');
        }
    }

    public function card()
    {
        if(request()->isPost()){
            $keyword = input('post.key');
            $page = input('page') ? input('page') : 1;
            $pageSize = input('limit') ? input('limit') : config('pageSize');

            if(!empty($keyword)){
                $map['title'] = array('like','%' . $keyword . '%');
            }

            $list = Db::name('card')->alias('c')
                ->field('c.*, u.id as uid  , u.avatar , u.nick_name , u.phone')
                ->join("users u", 'u.id = c.user_id', 'LEFT')
                ->where($map)
                ->order('c.id DESC')
                ->paginate(array('list_rows' => $pageSize,'page' => $page))->toArray();

            return [
                'code'=>0,
                'msg'=>'获取成功',
                'data'=>$list['data'],
                'count'=>$list['total'],
                'rel'=>1
            ];
        }else{
            return $this->fetch('moneydetail/card');
        }
    }
    public function cardDelAll(){
        $map['id'] = array('in',input('param.ids/a'));
        Db::name('card')->where($map)->delete();
        $result['code'] = 1;
        $result['msg'] = '删除成功！';
        $result['url'] = url('card',array('catid' => input('post.catid')));
        return $result;
    }
    public function check_card()
    {
        $status = input('status/d');
        if ($status != 2 && $status != 1) {
            $this->error('状态错误');
        }
        $id = input('id/d');
        $card = Db::name('card')->where(['id'=>$id])->find();
        if (!$card || $card['status'] != 0) {
            $this->error('数据没有找到或不能操作');
        }
        $res = Db::name('card')->where(['id'=>$id])->update(['status' => $status, 'check_time' => time()]);
        if (!$res) {
            $this->error('操作失败');
        }

        $this->success('操作成功', url('moneydetail/card'));
    }



}