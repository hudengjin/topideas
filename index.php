<?php 
/** 
 * Ideas.top 工作室
 *
 * @author Jobslong
 * 2015/1/23
 */

require 'wechatAPIService.php';

// Get query value
$payload = array(
	'token' => 'ideastop',
	'echostr' => $_GET['echostr'] | null,
	'signature' => $_GET['signature'] | null,
	'timestamp' => $_GET['timestamp'] | null,
	'once' => $_GET['onec'] | null,
);

// Instantiate 
$wechatAPIService = new wechatAPIService($payload); 

// Response for request
if( !$payload['echostr'] )
{
	$wechatAPIService->response();
} else {
	$wechatAPIService->valid();
}