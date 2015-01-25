<?php 
/** 
 * Ideas.top 工作室
 *
 * @author Jobslong
 * 2015/1/23
 */
require_once './vendor/autoload.php';
require_once './app/wechatAPIService.php';

// Get query value
$payload = array(
	'echostr' => $_GET['echostr'],
	'signature' => $_GET['signature'],
	'timestamp' => $_GET['timestamp'],
	'once' => $_GET['once'],
);

// Instantiate 
$wechatAPIService = new wechatAPIService($payload); 

$wechatAPIService->getAccessToken('appid', 'appsecret');

// 开发者通过检验signature对请求进行校验
// 若确认此次GET请求来自微信服务器
// 请原样返回echostr参数内容,则接入生效，成为开发者成功，否则接入失败
if( $wechatAPIService->checkSignature($payload) )
{
	$wechatAPIService->response();
} else {
	echo $payload['echostr'];
}