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
	'echostr' => $_GET['echostr'],
	'signature' => $_GET['signature'],
	'timestamp' => $_GET['timestamp'],
	'once' => $_GET['once'],
);

// Instantiate 
$wechatAPIService = new wechatAPIService($payload); 

// Response for request
if( $payload['echostr'] != NULL)
{
	$wechatAPIService->response();
} else {
	$wechatAPIService->valid();
}