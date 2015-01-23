<?php

/** 
 * Ideas.top 工作室
 *
 * @author Jobslong
 * 2015/1/23
 */

class WechatAPIService {

	protected $token;
	protected $echostr;
	protected $signature;
	protected $timestamp;
	protected $once;

	function __construct(Array $payload)
	{
		$this->once = $payload['once'];
		$this->token = $payload['token'];
		$this->echostr = $payload['echostr'];
		$this->signature = $payload['signature'];
		$this->timestamp = $payload['timestamp'];
	}

	public function response()
	{
		echo $this->echostr;
	}

	public function valid()
	{
		$token = $this->token;
		$timestamp = $this->timestamp;
		$once = $this->once;
		$echostr = $this->echostr;
		$signature = $this->signature;

        $tmpArr = array($token, $timestamp, $once);
        sort($tmpArr);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if($tmpStr == $signature){
            echo $echoStr;
            exit;
        }
	}
}