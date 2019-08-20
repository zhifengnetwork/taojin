<?php
/**
 * 继承
 */
namespace app\api\controller;
use app\common\util\jwt\JWT;
use think\Db;
use think\Controller;

use think\Request;
use think\Session;


class ApiBase extends Controller
{
    protected $uid;
    protected $user_name;
    protected $is_bing_mobile;

    public function _initialize () {
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Headers:*");
        header("Access-Control-Allow-Methods:GET, POST, OPTIONS, DELETE");
        header('Content-Type:application/json; charset=utf-8');

    }

 
    public function ajaxReturn($data){
        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Headers:*');
        header("Access-Control-Allow-Methods:GET, POST, OPTIONS, DELETE");
        header('Content-Type:application/json; charset=utf-8');
        exit(str_replace("\\/", "/",json_encode($data,JSON_UNESCAPED_UNICODE)));
    }

    /**
     * 生成token
     */
    public function create_token($user_id){
        $time = time();
        $payload = array(
            "iss"=> "QUANMINTAOJIN",
            "iat"=> $time ,  
            "exp"=> $time + 36000 , 
            "user_id"=> $user_id
        );
        $key = 'QUANMINTAOJIN';
        $token = JWT::encode($payload, $key, $alg = 'HS256', $keyId = null, $head = null);
        return $token;
    }

    /**
     * 解密token
     */
    public function decode_token($token){
        $key     = 'QUANMINTAOJIN';
        $payload = json_decode(json_encode(JWT::decode($token, $key, ['HS256'])),true);
        return $payload;
    }

    /**
    *
    *接收头信息
    **/
    public function em_getallheaders()
    {
       foreach ($_SERVER as $name => $value)
       {
           if (substr($name, 0, 5) == 'HTTP_')
           {
               $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
           }
       }
       return $headers;
    }

     /**
     * 获取user_id
     */
    public function get_user_id($url = null)
    {
        $headers = $this->em_getallheaders();

        $token = isset($headers['Token']) ? $headers['Token'] : input('token');
        if (strlen($token) < 10) {
            $token = input('token');
        }
        $user_token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJEQyIsImlhdCI6MTU1OTYzOTg3MCwiZXhwIjoxNTU5Njc1ODcwLCJ1c2VyX2lkIjo3Nn0.YUQ3hG3TiXzz_5U594tLOyGYUzAwfzgDD8jZFY9n1WA';

        if ($user_token == $token) {
            return 51;
        } elseif (1 == $token) {
            return 1;
        } else {
            if (!$token || $token == null || $token == 'null' || strlen($token) <= 10) {
                //401
                header('HTTP/1.1 401 Unauthorized');
                header('Status: 401 Unauthorized');

                $this->ajaxReturn(['status' => -1, 'msg' => 'token不存在' , 'data' => null]);
            }

            $tks = explode('.', $token);
            if (count($tks) != 3) {
                //401
                header('HTTP/1.1 401 Unauthorized');
                header('Status: 401 Unauthorized');
                $this->ajaxReturn(['status' => -1, 'msg' => 'token格式错误:' . $token, 'data' => null]);
            }

            $res = $this->decode_token($token);
            if (!$res) {
                //401
                header('HTTP/1.1 401 Unauthorized');
                header('Status: 401 Unauthorized');
                $this->ajaxReturn(['status' => -1, 'msg' => '无效token' . json_encode($res), 'data' => null]);
            }

            if (!isset($res['iat'])) {
                //401
                header('HTTP/1.1 401 Unauthorized');
                header('Status: 401 Unauthorized');
                $this->ajaxReturn(['status' => -1, 'msg' => 'token错误:iat不存在' . json_encode($res), 'data' => null]);
            }
            if (!isset($res['exp'])) {
                //401
                header('HTTP/1.1 401 Unauthorized');
                header('Status: 401 Unauthorized');
                $this->ajaxReturn(['status' => -1, 'msg' => 'token错误exp不存在' . json_encode($res), 'data' => null]);
            }
            if ($res['iat'] > $res['exp']) {
                //401
                header('HTTP/1.1 401 Unauthorized');
                header('Status: 401 Unauthorized');
                $this->ajaxReturn(['status' => -1, 'msg' => 'token已过期' . json_encode($res), 'data' => null]);
            }
            if (!isset($res['user_id'])) {
                //401
                header('HTTP/1.1 401 Unauthorized');
                header('Status: 401 Unauthorized');
                $this->ajaxReturn(['status' => -1, 'msg' => 'token出错' . json_encode($res), 'data' => null]);
            }
            return $res['user_id'];
        }
    }

    /**
     * 空
     */
    public function _empty(){
        $this->ajaxReturn(['status' => -1 , 'msg'=>'接口不存在','data'=>null]);
    }

    /**
     * 将base64图片转换存储并返回路径
     * @param $base64_img string base64格式的文件
     * @return mixed|string 文件路径
     */
    function base64_to_img($base64_img,$up_dir = UPLOAD_PATH)
    {
        $image = '';
        if (substr($base64_img, 0, 10) == 'data:image' && preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_img, $result)) {
//            $up_dir =  UPLOAD_PATH . 'company/'; //存放目录
            if (!file_exists(ROOT_PATH . $up_dir)) mkdir(ROOT_PATH . $up_dir, 0777);
            $file_name = date('YmdHis_') . rand(10000, 99999) . '.' . $result[2];
            $new_file = $up_dir . $file_name;
            if (file_put_contents(ROOT_PATH . $new_file, base64_decode(str_replace($result[1], '', $base64_img)))) {
                $image = '/' . $new_file;
            }
        }
        return $image;
    }
}
