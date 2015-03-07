<?php

/** 
 * Ideas.top 工作室
 *
 * @author Jobslong
 * 2015/1/23
 */

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Post\PostFile;
use Puzzle\Configuration as Config;
use Gaufrette\Filesystem as Filesystem;
use Gaufrette\Adapter\Local as Local;
use Desarrolla2\Cache\Cache;
use Desarrolla2\Cache\Adapter\File;

class WechatAPIService {

    /**
     * GuzzleHttp\Client
     * @var $httpClient
     */
    protected $httpClient;

    /**
     * Puzzle\Configuratio
     * @var $config
     */
    protected $config;

    /**
     * Desarrolla2\Cache\Cache
     * @var $cache
     */
    protected $cache;

    public function __construct()
    {
        $this->httpClient = new HttpClient;
        $fileSystem = new Filesystem(new Local(__DIR__ . '/../config'));
        $this->config = new Puzzle\Configuration\Yaml($fileSystem);

        $cacheDir = '/tmp';
        $adapter = new File($cacheDir);
        $adapter->setOption('ttl', 7200);
        $this->cache = new Cache($adapter);
    }

    public function checkRequest(Array $payload)
    {
        foreach ($payload as $key => $value) 
        {
            if( $value == NULL )
            {
                echo "缺少请求参数错误![" . $key . " 不能为空]";
                exit;
            }
        }
    }

    /**
     * 验证服务器地址的有效性
     * 
     * @param Array [timestamp,once,signature]
     * @return Boolean
     */
    public function checkSignature(Array $paramters)
    {
        // 将token、timestamp、nonce三个参数进行字典序排序
        $tmpArr = array($this->config->read('wechat/base/token'), 
            $paramters['timestamp'], $paramters['nonce']);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        // 将三个参数字符串拼接成一个字符串进行sha1加密
        $tmpStr = sha1($tmpStr);
        // 开发者获得加密后的字符串可与signature对比，标识该请求来源于微信
        return $tmpStr == $paramters['signature'] ? true : false;
    }

    /**
     * 获得access_token
     *
     * @param String $grant_type | default 'client_credential'
     * @return String
     */
    protected function getAccessToken($grant_type = 'client_credential')
    {
        if( !$this->cache->has('access_token') )
        {
            $request = $this->createRequest('access_token');
            $query = $request->getQuery();
            $query->set('grant_type', $grant_type);
            $query->set('appid', $this->config->read('wechat/base/appid'));
            $query->set('secret', $this->config->read('wechat/base/appsecret'));
            $response = $this->httpClient->send($request)->json();

            $this->checkResponse($response);

            // cache access_token
            $this->cache->set('access_token', 
                $response['access_token'], $response['expires_in']);
            return $response['access_token'];
        } else {
            return $this->cache->get('access_token');
        }
    }

    /**
     * 获得微信服务器IP列表
     *
     * @return Array IP list
     */
    public function getCallbackIPs()
    {
        $request = $this->createRequest('callback_ip');
        $query = $this->getAuthQuery($request);
        $response = $this->httpClient->send($request)->json();

        $this->checkResponse($response);

        return $response['ip_list'];
    }

    /**
     * 上传多媒体文件
     *
     * 上传的多媒体文件有格式和大小限制，如下：
     * 图片（image）: 1M，支持JPG格式
     * 语音（voice）：2M，播放长度不超过60s，支持AMR\MP3\SPEEX格式
     * 视频（video）：10MB，支持MP4格式
     * 缩略图（thumb）：64KB，支持JPG格式
     * 媒体文件在后台保存时间为3天，即3天后media_id失效
     * @param File $media
     * @param String $type:image|voice|video|thumb
     * @return Response
     */
    public function uploadMedia($media, $type)
    {
        $request = $this->createRequest('upload_media');
        $query = $this->getAuthQuery($request);
        $query->set('type', $type);

        $body = $request->getBody();
        $body->addFile(new PostFile('test', fopen($media, 'r')));
        $response = $this->httpClient->send($request);
        return $response->json();
    }

    public function downloadMedia($media_id)
    {
        $request = $this->createRequest('download_media');
        $query = $this->getAuthQuery($request);
        $query->set('media_id', $media_id);
        return $this->httpClient->send($request)->getBody();
    }

    protected function createRequest($api_name)
    {
        $scheme = $this->config->read('wechat/api/'. $api_name . '/scheme');
        $method = $this->config->read('wechat/api/'. $api_name . '/method');
        $url = $this->config->read('wechat/api/'. $api_name . '/url');
        $request = $this->httpClient->createRequest($method, $url);
        $request->setScheme($scheme);
        return $request;
    }

    protected function getAuthQuery($request)
    {
        $query = $request->getQuery();
        $query->set('access_token', $this->getAccessToken());
        return $query;
    }


    protected function checkResponse($response)
    {
        if( isset($response['errcode']) )
        {
            throw new Exception($response['errcode'] 
                . ':' . $response['errmsg']);
        }
    }

	public function response($postStr)
	{
        $msgBody = $this->parseXMLMessage($postStr);

        // dispatch message specify by messageTpye             
        switch ($msgBody['MsgType'])
        {
            case "text":
                $result = $this->receiveText($msgBody);
                break;
            default:
                $result = "unknown msg type: " . $msgBody['MsgType'];
                break;
        }

        return $result;
	}

    protected function parseXMLMessage($postStr)
    {
        if (!empty($postStr)) {
            $postObj = simplexml_load_string($postStr, 
                'SimpleXMLElement', LIBXML_NOCDATA);
            return (Array)$postObj;
        } else {
            echo "";
            exit;
        }
    }

    public function encryptMessage($postStr, $encrypt_type)
    {
        // todo
        return $postStr;
    }

	//接收文本消息
    private function receiveText($object)
    {
        $keyword = trim($object['Content']);
        //多客服人工回复模式
        if (strstr($keyword, "您好") || strstr($keyword, "你好") || strstr($keyword, "在吗")){
            $result = $this->transmitService($object);
        }
        //自动回复模式
        else{
            if (strstr($keyword, "文本")){
                $content = "这是个文本消息";
            }else if (strstr($keyword, "单图文")){
                $content = array();
                $content[] = array("Title"=>"单图文标题",  "Description"=>"单图文内容", "PicUrl"=>"http://discuz.comli.com/weixin/weather/icon/cartoon.jpg", "Url" =>"http://m.cnblogs.com/?u=txw1958");
            }else if (strstr($keyword, "图文") || strstr($keyword, "多图文")){
                $content = array();
                $content[] = array("Title"=>"多图文1标题", "Description"=>"", "PicUrl"=>"http://discuz.comli.com/weixin/weather/icon/cartoon.jpg", "Url" =>"http://m.cnblogs.com/?u=txw1958");
                $content[] = array("Title"=>"多图文2标题", "Description"=>"", "PicUrl"=>"http://d.hiphotos.bdimg.com/wisegame/pic/item/f3529822720e0cf3ac9f1ada0846f21fbe09aaa3.jpg", "Url" =>"http://m.cnblogs.com/?u=txw1958");
                $content[] = array("Title"=>"多图文3标题", "Description"=>"", "PicUrl"=>"http://g.hiphotos.bdimg.com/wisegame/pic/item/18cb0a46f21fbe090d338acc6a600c338644adfd.jpg", "Url" =>"http://m.cnblogs.com/?u=txw1958");
            }else if (strstr($keyword, "音乐")){
                $content = array();
                $content = array("Title"=>"最炫民族风", "Description"=>"歌手：凤凰传奇", "MusicUrl"=>"http://121.199.4.61/music/zxmzf.mp3", "HQMusicUrl"=>"http://121.199.4.61/music/zxmzf.mp3");
            }else{
                $content = date("Y-m-d H:i:s",time())."\n".$object['FromUserName']."\n技术支持 方倍工作室";
            }
            
            if(is_array($content)){
                if (isset($content[0]['PicUrl'])){
                    $result = $this->transmitNews($object, $content);
                }else if (isset($content['MusicUrl'])){
                    $result = $this->transmitMusic($object, $content);
                }
            }else{
                $result = $this->transmitText($object, $content);
            }
        }

        return $result;
    }

    //回复文本消息
    private function transmitText($object, $content)
    {
        $xmlTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[text]]></MsgType>
<Content><![CDATA[%s]]></Content>
</xml>";
        $result = sprintf($xmlTpl, $object['FromUserName'], $object['ToUserName'], time(), $content);
        return $result;
    }

    //回复图文消息
    private function transmitNews($object, $newsArray)
    {
        if(!is_array($newsArray)){
            return;
        }
        $itemTpl = "    <item>
        <Title><![CDATA[%s]]></Title>
        <Description><![CDATA[%s]]></Description>
        <PicUrl><![CDATA[%s]]></PicUrl>
        <Url><![CDATA[%s]]></Url>
    </item>
";
        $item_str = "";
        foreach ($newsArray as $item){
            $item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);
        }
        $xmlTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[news]]></MsgType>
<ArticleCount>%s</ArticleCount>
<Articles>
$item_str</Articles>
</xml>";

        $result = sprintf($xmlTpl, $object['FromUserName'], $object['ToUserName'], time(), count($newsArray));
        return $result;
    }

    //回复音乐消息
    private function transmitMusic($object, $musicArray)
    {
        $itemTpl = "<Music>
    <Title><![CDATA[%s]]></Title>
    <Description><![CDATA[%s]]></Description>
    <MusicUrl><![CDATA[%s]]></MusicUrl>
    <HQMusicUrl><![CDATA[%s]]></HQMusicUrl>
</Music>";

        $item_str = sprintf($itemTpl, $musicArray['Title'], $musicArray['Description'], $musicArray['MusicUrl'], $musicArray['HQMusicUrl']);

        $xmlTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[music]]></MsgType>
$item_str
</xml>";

        $result = sprintf($xmlTpl, $object['FromUserName'], $object['ToUserName'], time());
        return $result;
    }

    //回复多客服消息
    private function transmitService($object)
    {
        $xmlTpl = "<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[transfer_customer_service]]></MsgType>
</xml>";
        $result = sprintf($xmlTpl, $object['FromUserName'], $object['ToUserName'], time());
        return $result;
    }
}