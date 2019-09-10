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
            $start_time=input('post.start_time');
            $end_time=input('post.end_time');
            $ranking_status=input('post.ranking_status');
            $map=[];
            if(!empty($start_time)){
                if(!empty($start_time)){
                    if(strtotime($start_time)>strtotime($end_time)){
                        $map['rank_time']=['between',[strtotime($start_time),time()]];
                    }else{
                        $map['rank_time']=['between',[strtotime($start_time),strtotime($end_time)]];
                    }
                }else{
                    $map['rank_time']=['between',[strtotime($start_time),time()]];
                }
            }
            if(!empty($ranking_status)&&$ranking_status){
                if($ranking_status==2){
                    $map['r.rank_status']=0;
                }else{
                    $map['r.rank_status']=$ranking_status;
                }
            }
            if(!empty($keyword)){
                $map['r.id|u.phone'] = array('like','%' . $keyword . '%');
            }
//            $map['is_delete'] = 0;

            $list=Db::name('ranking')->alias('r')
                ->join('users u','u.id=r.user_id','LEFT')
                ->field('r.id,r.user_id,r.rank_time,r.add_time,r.rank_status,u.phone')
                ->where($map)
                ->order('id desc')
                ->paginate(array('list_rows' => $pageSize,'page' => $page))
                ->toArray();
//            $list = Db::name('ranking')
//                ->where($map)
//                ->order('id desc')
//                ->paginate(array('list_rows' => $pageSize,'page' => $page))
//                ->toArray();
//
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

    /**
     * 导出
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Writer_Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */

    function exportExcel_old(){
        $keyword=input('key');
        $start_time=input('start_time');
        $end_time=input('end_time');

        $where=[];
        if(!empty($start_time)){
            if(!empty($start_time)){
                if(strtotime($start_time)>strtotime($end_time)){
                    $where['rank_time']=['between',[strtotime($start_time),time()]];
                }else{
                    $where['rank_time']=['between',[strtotime($start_time),strtotime($end_time)]];
                }
            }else{
                $where['rank_time']=['between',[strtotime($start_time),time()]];
            }
        }

        if(!empty($keyword)){
            $where['id'] = array('like','%' . $keyword . '%');
        }


        error_reporting(0);
        set_time_limit(0);
        vendor('PHPExcel.PHPExcel');

        //导入Excel文件
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);
        // 设置sheet名
        $objPHPExcel->getActiveSheet()->setTitle('xx列表');

        // 设置表格宽度
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(20);
//        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(20);


        // 列名表头文字加粗
        $objPHPExcel->getActiveSheet()->getStyle('A1:J1')->getFont()->setBold(true);
        // 列表头文字居中
        $objPHPExcel->getActiveSheet()->getStyle('A1:J1')->getAlignment()
            ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        // 列名赋值
        $objPHPExcel->getActiveSheet()->setCellValue('A1', '编号');
        $objPHPExcel->getActiveSheet()->setCellValue('B1', '用户id');
        $objPHPExcel->getActiveSheet()->setCellValue('C1', '手机号码');
        $objPHPExcel->getActiveSheet()->setCellValue('D1', '排位状态');
        $objPHPExcel->getActiveSheet()->setCellValue('E1', '排位时间');
        $objPHPExcel->getActiveSheet()->setCellValue('F1', '下单时间');
//        $objPHPExcel->getActiveSheet()->setCellValue('G1', '审核时间');

        $count=Db::name('ranking')->where($where)->count();
        $start=0;
        $limit=500;
        $res_num=0;
        $is_end=false;
//        if($count>20000){
//            echo '数据量过大，不能导出！数量：'.$count;
//            die;
//        }


        // 数据起始行
        $row_num = 2;
        while (!$is_end){
            $res=Db::name('ranking')->alias('r')
                ->join('users u','u.id=r.user_id','LEFT')
                ->field('r.id,r.user_id,r.rank_time,r.add_time,r.rank_status,u.phone')
                ->where($where)->limit($start,$limit)->select();
            if($res_num>$count){//大于所有。结束
                $is_end=true;
            }
            $start=$start+$limit;//起始数量增加
            $res_num=$res_num+$limit;//记录现在数据量
            // 向每行单元格插入数据
            foreach($res as $value)
            {
                // 设置所有垂直居中
                $objPHPExcel->getActiveSheet()->getStyle('A' . $row_num . ':' . 'J' . $row_num)->getAlignment()
                    ->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                // 设置价格为数字格式
                $objPHPExcel->getActiveSheet()->getStyle('D' . $row_num)->getNumberFormat()
                    ->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_NUMBER_00);
                // 居中
                $objPHPExcel->getActiveSheet()->getStyle('E' . $row_num . ':' . 'H' . $row_num)->getAlignment()
                    ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

                // 设置单元格数值
                $objPHPExcel->getActiveSheet()->setCellValue('A' . $row_num, $value['id']);
                $objPHPExcel->getActiveSheet()->setCellValue('B' . $row_num, $value['user_id']);
                $objPHPExcel->getActiveSheet()->setCellValue('C' . $row_num, $value['phone']);
                $objPHPExcel->getActiveSheet()->setCellValue('D' . $row_num, $value['rank_status'] ? '出局' : '未出局');
                $objPHPExcel->getActiveSheet()->setCellValue('E' . $row_num, date('Y-m-d h:i:s',$value['rank_time']));
                $objPHPExcel->getActiveSheet()->setCellValue('F' . $row_num, date('Y-m-d h:i:s',$value['add_time']));
//            $objPHPExcel->getActiveSheet()->setCellValue('G' . $row_num, date('Y-m-d h:i:s',$value['statetime']));
                $row_num++;
            }
        }


        $outputFileName = '排位订单' . time() . '.xls';
        $xlsWriter = new \PHPExcel_Writer_Excel5($objPHPExcel);
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header('Content-Disposition:inline;filename="' . $outputFileName . '"');
        header("Content-Transfer-Encoding: binary");
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Pragma: no-cache");
        $xlsWriter->save("php://output");
        exit();
    }


    /*
     * 包时间段设置
     */
    public function time_slot(){

        if(request()->isPost()){
            $keyword = input('post.key');
            $page = input('page') ? input('page') : 1;
            $pageSize = input('limit') ? input('limit') : config('pageSize');
            $map=[];
            if(!empty($keyword)){
                $map['id'] = array('like','%' . $keyword . '%');
            }

            $list = Db::name('time_slot')
                ->where($map)
                ->order('id desc')
                ->paginate(array('list_rows' => $pageSize,'page' => $page))
                ->toArray();
            foreach ($list['data'] as $key=>$value){
                $list['data'][$key]['add_time']=date('Y-m-d H:i:s',$list['data'][$key]['add_time']);
            }
            return [
                'code' => 0,
                'msg' => '获取成功',
                'data' => $list['data'],
                'count' => $list['total'],
                'rel' => 1,
            ];

        }else{
            return $this->fetch('ranking/time_slot');
        }
    }
    /*
     * 删除
     */
    public function timeDel(){
        $id = input('post.id');
        if(Db::name('time_slot')->where(array('id' => $id))->delete()){
            return ['code'=>1,'msg' => '删除成功！','url' => url('time_slot')];
        }else{
            return ['code'=>0,'msg' => '删除失败！','url' => url('time_slot')];
        }
    }
    /*
     * 是否启用
     */
    public function tiemState(){
        $id=input('post.id');
        $is_open=input('post.status');
        if (empty($id)){
            $result['status'] = 0;
            $result['info'] = 'ID不存在!';
            $result['url'] = url('time_slot');
            return $result;
        }
        db('time_slot')->where('id='.$id)->update(['status'=>$is_open]);
        $result['status'] = 1;
        $result['info'] = '用户状态修改成功!';
        $result['url'] = url('time_slot');
        return $result;
    }
    /*
     * 添加时间段
     */
    public function timeAdd(){
        if(request()->isPost()){
            $data = input('post.');
//            $data['open'] = input('post.open') ? input('post.open') : 0;
            $data['add_time']=time();
            db('time_slot')->insert($data);
            $result['msg'] = '时间段添加成功!';
            $result['url'] = url('time_slot');
            $result['code'] = 1;
            return $result;
        }else{
            $this->assign('title',lang('add') . "时间段");
            $this->assign('info','null');
            return $this->fetch('time_form');
        }
    }

    public function timeEdit(){
        if(request()->isPost()){
            $data = input('post.');
            db('time_slot')->update($data);
            $result['msg'] = '时间段修改成功!';
            $result['url'] = url('userGroup');
            $result['code'] = 1;
            return $result;
        }else{
            $map['id'] = input('param.id');
            $info = db('time_slot')->where($map)->find();
            $this->assign('title',lang('edit') . "时间段");
            $this->assign('info',json_encode($info,true));
            return $this->fetch('time_form');
        }
    }
}