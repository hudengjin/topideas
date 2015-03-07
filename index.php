<?php 
/** 
 * Ideas.top 工作室
 *
 * @author Jobslong
 * 2015/1/23
 */
require_once './vendor/autoload.php';
require_once './app/wechatAPIService.php';

date_default_timezone_set('Asia/Shanghai');

// Get query value
$payload = array(
	'echostr' => $_GET['echostr'],
	'signature' => $_GET['signature'],
	'timestamp' => $_GET['timestamp'],
	'nonce' => $_GET['nonce'],
);

// Instantiate 
$wechatAPIService = new wechatAPIService($payload); 

$wechatAPIService->checkRequest($payload);

// 开发者通过检验signature对请求进行校验
// 若确认此次GET请求来自微信服务器
// 请原样返回echostr参数内容,则接入生效，成为开发者成功，否则接入失败
if( $wechatAPIService->checkSignature($payload) )
{
	// 在安全模式或兼容模式下，url上会新增两个参数:
	// encrypt_type:表示加密类型
	// msg_signature:表示对消息体的签名
	// url上无encrypt_type参数
	// 或者其值为raw时表示为不加密；
	// encrypt_type为aes时，表示aes加密（暂时只有raw和aes两种值)
	$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
	if (!($_GET['encrypt_type'] == NULL || $_GET['encrypt_type'] == 'raw'))
	{
		// 对消息进行解密操作，并对回复消息进行加密
		$postStr = $wechatAPIService->encryptMessage($postStr, 
			$_GET['encrypt_type']);
	}
	echo $wechatAPIService->response($postStr);
} else {
	echo $payload['echostr'];
}