<?php

namespace app\common\model;

use think\Db;
use think\Model;

class Withdraw extends Model
{
    protected $updateTime = false;

    protected $autoWriteTimestamp = true;

    public static $status_list = [
        -1 => '审核失败',
        0 => '申请中',
        1 => '审核通过',
    ];

    public static $type_list = [
        2 => '银行',
        1 => '支付宝',
    ];

    public function getTypeAttr($value)
    {
        return self::$type_list[$value];
    }

    public static function getStatusTextBy($value)
    {
        return self::$status_list[$value];
    }

    public function getTypeTextAttr($value, $data)
    {
        return self::$type_list[$data['type']];
    }

    public function getStatusTextAttr($value, $data)
    {
        return self::$status_list[$data['status']];
    }

    public function getTypeDataAttr($value, $data)
    {
        $res = [];
        if ($data['type'] == 2) {
            $res = Db::name('card')->field('bank,name,number')->where(['info' => $data['openid']])->find();
            $res['type'] = '银行卡';
        } elseif ($data['type'] == 1) {
            $res = ['type' => '支付宝', 'number' => $data['openid']];
        }
        return $res;
    }

    /**
     * 提现手续费费率,默认返回百分比
     * @param string $unit 'percent'、'decimals'
     * @return float|int
     */
    static function getWDRate($unit = 'percent')
    {
        $rate = db('config')->where(['inc_type'=>'taojin','name'=>'withdraw_percent'])->value('value');
        return $unit == 'decimals' ? ($rate / 100) : $rate;
    }

}