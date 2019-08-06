<?php
namespace app\api\controller;
use think\Db;

class Index extends ApiBase
{
    public function index()
    {
       
        echo "API";

    }


    public function test(){
        // $user_id = 1;
        // echo $this->create_token($user_id);


        echo $this->get_user_id();
    }


}
