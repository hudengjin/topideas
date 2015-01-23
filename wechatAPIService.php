<?php

/** 
 * Ideas.top 工作室
 *
 * @author Jobslong
 * 2015/1/23
 */

class WechatAPIService {

	protected $payload = array();

	function __construct(Array $payload)
	{
		$this->$payload = $payload;
	}

	public function response()
	{
		return "call success!";
	}

	public function valid()
	{
		return 'valid success';
	}
}