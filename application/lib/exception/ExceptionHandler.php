<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/8/22 0022
 * Time: 下午 15:54
 */

namespace app\lib\exception;


use think\Exception;
use think\exception\Handle;

class ExceptionHandler extends Handle
{
    public function render(Exception $ex)
    {
        return json('------');
    }
}