<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/2 0002
 * Time: 下午 16:47
 */

namespace app\api\service;


use app\lib\exception\WeChatException;
use think\Exception;
use app\api\model\User as UserModel;

class UserToken
{

    protected $code;
    protected $wxLoginUrl;
    protected $wxAppID;
    protected $wxAppSecret;

    function __construct($code)
    {
        $this->code        = $code;
        $this->wxAppID     = config('wx.app_id');
        $this->wxAppSecret = config('wx.app_secret');
        $this->wxLoginUrl  = sprintf(
            config('wx.login_url'), $this->wxAppID, $this->wxAppSecret, $this->code);
    }

    /**
     * 登陆
     * 思路1：每次调用登录接口都去微信刷新一次session_key，生成新的Token，不删除久的Token
     * 思路2：检查Token有没有过期，没有过期则直接返回当前Token
     * 思路3：重新去微信刷新session_key并删除当前Token，返回新的Token
     */
    public function get($code)
    {
        $result = curl_get($this->wxLoginUrl); //发送http请求

        // 注意json_decode的第二个参数true
        // 这将使字符串被转化为数组而非对象

        $wxResult = json_decode($result, true);
        if (empty($wxResult)) {
            // 为什么以empty判断是否错误，这是根据微信返回
            // 规则摸索出来的
            // 这种情况通常是由于传入不合法的code
            throw new Exception('获取session_key及openID时异常，微信内部错误');
        } else {
            // 建议用明确的变量来表示是否成功
            // 微信服务器并不会将错误标记为400，无论成功还是失败都标记成200
            // 这样非常不好判断，只能使用errcode是否存在来判断
            $loginFail = array_key_exists('errcode', $wxResult);
            if ($loginFail) {
                $this->processLoginError($wxResult);
            } else {
                return $this->grantToken($wxResult);
            }
        }
    }

    /*
     * 颁发令牌
     * */
    private function grantToken($wxResult)
    {
        //1.拿到openid
        $openid = $wxResult['openid'];
        //2.查看数据库,openid是否存在
        $user = UserModel::getByOpenID($openid);
        //3.如果存在 则不处理, 不存在新增一条user数据
        if ($user) {
            $uid = $user->id;
        } else {
            $uid = $this->newUser($openid);
        }
        //4.生成令牌,准备缓存数据,写入缓存
        $cacheValue = $this->prepareCachedValue($wxResult, $uid);
        //key 令牌
        //value : wxResult,uid,scope权限--作用域 数字越大,权限越大
        //5.把令牌返回到客户端去
    }

    // 写入缓存
    private function saveToCache($wxResult)
    {
        $key       = self::generateToken();
        $value     = json_encode($wxResult);
        $expire_in = config('setting.token_expire_in');
        $result    = cache($key, $value, $expire_in);

        if (!$result) {
            throw new TokenException([
                'msg'       => '服务器缓存异常',
                'errorCode' => 10005
            ]);
        }
        return $key;
    }

    private function prepareCachedValue($wxResult, $uid)
    {
        $cachedValue          = $wxResult;
        $cachedValue['uid']   = $uid;
        $cachedValue['scope'] = ScopeEnum::User;
        return $cachedValue;
    }

    /*
     * 写入记录
     * */
    private function newUser($openid)
    {
        $user = UserModel::create(array('openid' => $openid));
        return $user->id;
    }

    // 处理微信登陆异常
    // 哪些异常应该返回客户端，哪些异常不应该返回客户端
    private function processLoginError($wxResult)
    {
        throw new WeChatException(
            [
                'msg'       => $wxResult['errmsg'],
                'errorCode' => $wxResult['errcode']
            ]);
    }

    // 处理微信登陆异常
    // 那些异常应该返回客户端，那些异常不应该返回客户端
    // 需要认真思考
    private function processLoginError($wxResult)
    {
        throw new WeChatException(
            [
                'msg'       => $wxResult['errmsg'],
                'errorCode' => $wxResult['errcode']
            ]);
    }
}