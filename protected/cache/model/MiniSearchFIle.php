<?php
/**
 * 缓存miniyun_users表的记录，V1.2.0该类接管所有miniyun_users的操作
 * 按客户端请求方式，逐渐增加记录到内存
 * 直接查询Cache,而非DB
 * 数据更新首先更新Cache，然后是DB
 * @author app <app@miniyun.cn>
 * @link http://www.miniyun.cn
 * @copyright 2014 Chengdu MiniYun Technology Co. Ltd.
 * @license http://www.miniyun.cn/license.html
 * @since 1.6
 */
class MiniSearchFile extends MiniCache{
    /**
     *
     * Cache Key的前缀
     * @var string
     */
    private static $CACHE_KEY = "cache.model.searchFiles";

    /**
     *  静态成品变量 保存全局实例
     *  @access private
     */
    static private $_instance = null;

    /**
     *  私有化构造函数，防止外界实例化对象
     */
    private function  __construct()
    {
        parent::MiniCache();
    }

    /**
     * 静态方法, 单例统一访问入口
     * @return object  返回对象的唯一实例
     */
    static public function getInstance()
    {
        if (is_null(self::$_instance) || !isset(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * 将文本内容存入数据库
     */
    public function  saveTxt($confContent,$fileHash){
        $item = new SearchFile();
        $item['content']=$confContent;
        $item['file_signature']=$fileHash;
        $item['created_at'] = date("Y-m-d H:i:s");
        $item['updated_at'] = date("Y-m-d H:i:s");
        $item->save();
        return true;
    }

}