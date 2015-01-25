<?php

/** 
 * Ideas.top 工作室
 *
 * @author Jobslong
 * 2015/1/23
 */

use GuzzleHttp\Client as HttpClient;
use Puzzle\Configuration as Config;
use Gaufrette\Filesystem as Filesystem;
use Gaufrette\Adapter\Local as Local;

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

    public function __construct()
    {
        $this->httpClient = new HttpClient;
        $fileSystem = new Filesystem(new Local(__DIR__ . '/../config'));
        $this->config = new Puzzle\Configuration\Yaml($fileSystem);
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
            $paramters['timestamp'], $paramters['once']);
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
     * @param String $appid
     * @param String $appsecret
     * @param String $grant_type | default 'client_credential'
     * @return String
     */
    public function getAccessToken($appid, $appsecret, 
        $grant_type = 'client_credential')
    {
        $response = $this->httpClient->get(
            $this->config->read('wechat/api/access_token/url'), 
            ['query' => [
                'grant_type' => $grant_type,
                'appid' => $appid,
                'secret' => $appsecret
            ]])->json();

        if( isset($response['errcode']) )
        {
            throw new Exception($response['errcode'] 
                . ':' . $response['errmsg']);
        }

        return $response['access_token'];
    }

	public function response()
	{
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        if (!empty($postStr)){
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $RX_TYPE = trim($postObj->MsgType);
             
            //消息类型分离
            switch ($RX_TYPE)
            {
                case "text":
                    $result = $this->receiveText($postObj);
                    break;
                default:
                    $result = "unknown msg type: ".$RX_TYPE;
                    break;
            }
            echo $result;
        }else {
            echo "";
            exit;
        }
	}

	//接收文本消息
    private function receiveText($object)
    {
        $keyword = trim($object->Content);
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
                $content = date("Y-m-d H:i:s",time())."\n".$object->FromUserName."\n技术支持 方倍工作室";
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
        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time(), $content);
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

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time(), count($newsArray));
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

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
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
        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }
}