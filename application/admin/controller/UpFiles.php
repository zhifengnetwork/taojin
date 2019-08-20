<?php

namespace app\admin\controller;
class UpFiles extends Common
{
    public function upload(){
        // 获取上传文件表单字段名
        $fileKey = array_keys(request()->file());
        // 获取表单上传文件
        $file = request()->file($fileKey['0']);
        // 移动到框架应用根目录/public/uploads/ 目录下
        $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
        if($info){
            $result['code'] = 1;
            $result['info'] = '图片上传成功!';
            $path = str_replace('\\','/',$info->getSaveName());
            $result['url'] = '/uploads/' . $path;
            return $result;
        }else{
            // 上传失败获取错误信息
            $result['code'] = 0;
            $result['info'] = '图片上传失败!';
            $result['url'] = '';
            return $result;
        }
    }

    public function file(){
        $fileKey = array_keys(request()->file());
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file($fileKey['0']);
        // 移动到框架应用根目录/public/uploads/ 目录下
        $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');

        if($info){
            $result['code'] = 0;
            $result['info'] = '文件上传成功!';
            $path = str_replace('\\','/',$info->getSaveName());

            $result['url'] = '/uploads/' . $path;
            $result['ext'] = $info->getExtension();
            $result['size'] = byte_format($info->getSize(),2);
            return $result;
        }else{
            // 上传失败获取错误信息
            $result['code'] = 1;
            $result['info'] = '文件上传失败!';
            $result['url'] = '';
            return $result;
        }
    }

    /**
     * 导入淘宝excel订单
     **/
    public function fileexcel(){

        $fileKey = array_keys(request()->file());
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file($fileKey['0']);
        // 移动到框架应用根目录/public/uploads/ 目录下
        $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');

        if($info){
            $result['code'] = 0;
            $result['info'] = '文件上传成功!';
            $path = str_replace('\\','/',$info->getSaveName());

            $result['url'] = '/uploads/' . $path;
            $result['ext'] = $info->getExtension();
            $result['size'] = byte_format($info->getSize(),2);
            //return $result;

            $pathinfo = pathinfo(__FILE__);
            //这个地方需要动态配置
            $fileurl = "/phpstudy/www/public".$result['url'];

            //leapmary//上传订单#######
            //待解决两次引入autoload.php的问题
            //$re = require_once("myclass/qihang_get_orderinfo.php");
            //由于时间紧迫采用外部文件，并未整合

            $data = array(
                "fileurl"=>$fileurl
            );

            $url = "http://".$_SERVER['HTTP_HOST']."/myclass/qihang_get_orderinfo.php";
            $ch = curl_init($url);
            curl_setopt($ch,CURLOPT_HEADER,0);
            curl_setopt($ch,CURLOPT_TIMEOUT,300);  //定义超时300秒钟
            $mes = curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch,CURLOPT_POST,1);
            curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
            curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false); //终端不验证curl
            curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
            $result = curl_exec($ch);
            curl_close ( $ch );

            //leapmary//订单计算########################
            $confing = action('common/Alluse/getconfig',['inc_type' => 'app']);
            $tbkorder = db('tbkorder')
                ->where("returns",null)
                ->select();
            foreach($tbkorder as $k=>$v){
                $return = floor($v['commission'] * $confing['selfrebate']) / 100;
                if(empty($return)){
                    $return = 0;
                }
                db('tbkorder')->where('id',$v['id'])->update([
                    'returns'=>$return
                ]);
            }
            //leapmary//订单计算########################

            print_r(1);
            exit;
        }else{
            // 上传失败获取错误信息
            $result['code'] = 1;
            $result['info'] = '文件上传失败!';
            $result['url'] = '';
            return $result;
        }

    }

    /**
     * 导入淘宝excel订单
     **/
    public function fileexcelooooooooooooooooold(){
        $fileKey = array_keys(request()->file());
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file($fileKey['0']);
        // 移动到框架应用根目录/public/uploads/ 目录下
        $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');

        if($info){
            $result['code'] = 0;
            $result['info'] = '文件上传成功!';
            $path = str_replace('\\','/',$info->getSaveName());

            $result['url'] = '/uploads/' . $path;
            $result['ext'] = $info->getExtension();
            $result['size'] = byte_format($info->getSize(),2);
            //return $result;

            //leapmary//执行订单导入功能#######
            $per = M("basic")->where("basic_id=1")->getField("commission_percent");

            if (!empty($_FILES)){

                $ExlData = importExcel("upfile");

                for($i = 2,$j=0;$i<sizeof($ExlData);$i++,$j++){

                    if (!$ExlData[$i]['A']) {

                        continue;
                    }
                    $add_time = strtotime($ExlData[$i]['A']);

                    $click_time = strtotime($ExlData[$i]['B']);

                    /*if (!$ExlData[$i]['S']) { //佣金=付款金额*佣金比率/100取2位小数

                        $ExlData[$i]['S'] = round($ExlData[$i]['M']*$ExlData[$i]['R']/100,2);
                    }*/
                    $returns = round($ExlData[$i]['N']*$per/100,3);

                    $dataList[] = array(

                        'add_time'           => $add_time, //创建时间
                        'click_time'         => $click_time, //点击时间
                        'good_title'         => $ExlData[$i]['C'], //商品信息
                        'good_id'            => $ExlData[$i]['D'], //商品ID
                        'mess'               => $ExlData[$i]['E'], //掌柜旺旺
                        'shop'               => $ExlData[$i]['F'], //所属店铺
                        'good_num'           => $ExlData[$i]['G'], //商品数
                        'good_price'         => $ExlData[$i]['H'], //商品单价
                        'order_state'        => $ExlData[$i]['I'], //订单状态
                        'order_type'         => $ExlData[$i]['J'], //订单类型
                        'income'             => $ExlData[$i]['K'], //收入比率
                        'divide'             => $ExlData[$i]['L'], //分成比率
                        'amount_pay'         => $ExlData[$i]['M'], //付款金额
                        'evaluation'         => $ExlData[$i]['N'], //效果预估
                        'settlement'         => $ExlData[$i]['O'], //结算金额
                        'estimate'           => $ExlData[$i]['P'], //预估收入
                        'settlement_time'    => $ExlData[$i]['Q'], //结算时间
                        'commission_percent' => $ExlData[$i]['R'], //佣金比率
                        'commission'         => $ExlData[$i]['S'], //佣金金额
                        'pension_percent'    => $ExlData[$i]['T'], //补贴比率
                        'pension'            => $ExlData[$i]['U'], //补贴金额
                        'pension_type'       => $ExlData[$i]['V'], //补贴类型
                        'roof'               => $ExlData[$i]['W'], //成交平台
                        'source'             => $ExlData[$i]['X'], //第三方服务来源
                        'order_sn'           => $ExlData[$i]['Y'], //订单编号
                        'cate'               => $ExlData[$i]['Z'], //类目名称
                        'media_id'           => $ExlData[$i]['AA'], //来源媒体ID
                        'media'              => $ExlData[$i]['AB'], //来源媒体名称
                        'adv_id'             => $ExlData[$i]['AC'], //广告位ID
                        'adv'                => $ExlData[$i]['AD'], //广告位名称
                        'returns'            => $returns,
                    );
                }
                //var_dump($dataList);die();
                $model = M('tbk_order');

                $tmp = $this->addAll($model,$dataList);


                if (empty($tmp)) {

                    $model->commit();

                    admin_logs("导入淘宝订单",1);

                    $this->success("导入成功");

                }else{

                    $model->rollback();

                    $this->error("导入失败，原因可能是excel表中有些用户已被注册。或表格格式错误");
                }

            }else{
                $this->error("请上传文件");
            }
            //leapmary//执行订单导入功能#######

        }else{
            // 上传失败获取错误信息
            $result['code'] = 1;
            $result['info'] = '文件上传失败!';
            $result['url'] = '';
            return $result;
        }
    }

    public function pic(){
        // 获取上传文件表单字段名
        $fileKey = array_keys(request()->file());
        // 获取表单上传文件
        $file = request()->file($fileKey['0']);
        // 移动到框架应用根目录/public/uploads/ 目录下
        $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
        if($info){
            $result['code'] = 1;
            $result['info'] = '图片上传成功!';
            $path = str_replace('\\','/',$info->getSaveName());
            $result['url'] = '/uploads/' . $path;
            return json_encode($result,true);
        }else{
            // 上传失败获取错误信息
            $result['code'] = 0;
            $result['info'] = '图片上传失败!';
            $result['url'] = '';
            return json_encode($result,true);
        }
    }

    //编辑器图片上传
    public function editUpload(){
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');
        // 移动到框架应用根目录/public/uploads/ 目录下
        $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
        if($info){
            $result['code'] = 0;
            $result['msg'] = '图片上传成功!';
            $path = str_replace('\\','/',$info->getSaveName());
            $result['data']['src'] = __PUBLIC__ . '/uploads/' . $path;
            $result['data']['title'] = $path;
            return json_encode($result,true);
        }else{
            // 上传失败获取错误信息
            $result['code'] = 1;
            $result['msg'] = '图片上传失败!';
            $result['data'] = '';
            return json_encode($result,true);
        }
    }

    //多图上传
    public function upImages(){
        $fileKey = array_keys(request()->file());
        // 获取表单上传文件
        $file = request()->file($fileKey['0']);
        // 移动到框架应用根目录/public/uploads/ 目录下
        $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
        if($info){
            $result['code'] = 0;
            $result['msg'] = '图片上传成功!';
            $path = str_replace('\\','/',$info->getSaveName());
            $result["src"] = '/uploads/' . $path;
            return $result;
        }else{
            // 上传失败获取错误信息
            $result['code'] = 1;
            $result['msg'] = '图片上传失败!';
            return $result;
        }
    }
}