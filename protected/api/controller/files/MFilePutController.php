<?php
/**
 * Miniyun 文件上传服务主要入口地址,实现文件下载
 * @author app <app@miniyun.cn>
 * @link http://www.miniyun.cn
 * @copyright 2014 Chengdu MiniYun Technology Co. Ltd.
 * @license http://www.miniyun.cn/license.html 
 * @since 1.6
 */
class MFilePutController extends MApplicationComponent implements MIController{
    /**
     * 控制器执行主逻辑函数
     */
    public function invoke($uri=null)
    {
        $this->setAction(MConst::CREATE_FILE);
        set_time_limit(500); // 设置请求超时
        // 调用父类初始化函数，注册自定义的异常和错误处理逻辑
        parent::init();
        //只接受PUT或者 POST请求
        $method = isset($_SERVER["REQUEST_METHOD"]) ? $_SERVER["REQUEST_METHOD"] : NULL;
        if ($method != "POST" && $method != "PUT") {
            throw new MFilesException(Yii::t('api',MConst::REQUEST_MOTHOD_ERROR), MConst::HTTP_CODE_405);
        }
        
        // 解析文件路径，若返回false，则错误处理
        $url_manager = new MUrlManager();
        $path = $url_manager->parsePathFromUrl($uri);
        $root = $url_manager->parseRootFromUrl($uri);
        if ($path == false || $root == false) {
            throw new MFilesException(Yii::t('api',MConst::PATH_ERROR), MConst::HTTP_CODE_411);
        }
        // web端上传零字节文件
        if (MUserManager::getInstance()->isWeb() === true) {
            $this->handleStoreData();
            $_REQUEST['hash'] = $this->signature;
            $_REQUEST['size'] = $this->_size;
        }
        
        $this->_size = -1;
        // 老版本
        if (isset($_SERVER['HTTP_IF_MATCH']) && isset($_SERVER['HTTP_X_CONTENT_LENGTH']) && isset($_SERVER['HTTP_CONTENT_RANGE'])) {
            $this->signature = $_SERVER['HTTP_IF_MATCH'];
            $btupload = new MBtUpload();
            $btupload->invoke();
            $store_path = $btupload->cache;
            //清除缓存并再次检查文件大小
            clearstatcache();
            //data源处理对象
            $dataObj = Yii::app()->data;
            $size = $dataObj->size($store_path);
            $this->_size = $size;
        }else {
            // 如果文件已经上传成功, 返回文件已经上传成功
            $this->handleSpikeFile($uri);
            
            $hash   = isset($_REQUEST['hash']) ? $_REQUEST['hash'] : '';
            $size   = isset($_REQUEST['size']) ? $_REQUEST['size'] : NULL;
            $offset = isset($_REQUEST['offset']) ? $_REQUEST['offset'] : 0;
            if (empty($hash) || $size === NULL || $size < 0)
                throw new MFilesException(Yii::t('api',MConst::PARAMS_ERROR . 'Missing parameter'), MConst::HTTP_CODE_400);
            
            $store_path = '';
            if ($offset < 0) {
                throw new MFilesException(Yii::t('api',MConst::PARAMS_ERROR . 'error parameter'), MConst::HTTP_CODE_400);
            }
            
            // 处理分块上传逻辑部分，通过插件内部的

            if ($offset == 0) {
                $store_path = $this->handleEntireFile($hash, $size);
            } else {
                $store_path = $this->handleBreakPointFile($hash, $size, $offset);
            }
        }
        // 初始化创建文件公共类句柄
        $create_handler = MFilesCommon::initMFilesCommon();
        
        $path        = "/" . $path;
        $path_info   = MUtils::pathinfo_utf($path);
        $file_name   = $path_info["basename"];
        $parent_path = $path_info["dirname"];
        
        $create_handler->size           = $this->_size;
        $create_handler->parent_path    = MUtils::convertStandardPath($parent_path);;
        $create_handler->file_name      = $file_name;
        $create_handler->root           = $root;
        $create_handler->path           = MUtils::convertStandardPath($path);
        $create_handler->type           = CUtils::mime_content_type($file_name);
        // 文件不存在,保存文件
        $create_handler->saveFile($store_path, $this->signature, $this->_size, false);
        // 保存文件meta
        $create_handler->saveFileMeta();
        if (MUserManager::getInstance()->isWeb() === true)
        {
            $create_handler->buildWebResponse();
            return ;
        }
        $create_handler->buildResult();
    }
    
    /**
     * 
     * 读取文件，保存到指定位置
     * @throws MFilesException
     */
    private function handleStoreData() {
        //data源处理对象
        $dataObj = Yii::app()->data;
        // 采用文件的方式，降低内存消耗
        $handle = fopen("php://input", "rb");
        $data = fread($handle, 8096);
        $tmp = DOCUMENT_TEMP . "/" . MiniUtil::getEventRandomString();
        if (file_exists(dirname($tmp)) === false) {
                MUtils::MkDirs(dirname($tmp));
        }
        
        $file = fopen($tmp, "wb");
        while ($data) {
            fwrite($file, $data);
            $data = fread($handle, 8096);
        }
        fclose($handle);
        fclose($file);
        // 检查文件上传错误
        $this->_size = filesize($tmp);
        $this->signature = MiniUtil::getFileHash($tmp);
        
        // 如果文件不存在则保存
        $store_path = MiniUtil::getPathBySplitStr($this->signature);
        if ($dataObj->exists($store_path) === false) {
            // 创建父目录
            if ($dataObj->exists(dirname($store_path)) === false) {
                MUtils::MkDirs(dirname($store_path));
            }
            
            if ($dataObj->put($tmp, $store_path, true) == false) {
                throw new MFilesException(Yii::t('api',"The file upload error!"), MConst::HTTP_CODE_400);
            }
        }
        @unlink($tmp);
        return $store_path;
    }
    
    /**
     * 
     * 秒传方式传送文件，处理已经存在的文件
     * @param string uri 请求地址
     * @since 1.0.0
     */
    public function handleSpikeFile($uri) {
        $spike = new MSpike();
        $spike->invoke($uri);
        if ($spike->status) {
            exit();
        }
    }
    
    /**
     * 使用put方法上传整文件
     */
    private function handleEntireFile($hash, $size) {
        $dataObj = Yii::app()->data;
        $handle = $this->getInputFileHandle();
        $cache = '/cache/' . MiniUtil::getPathBySplitStr($hash);

        if ($dataObj->exists(dirname($cache)) === false) {
            $dataObj->mkdir(dirname($cache));
        }

        // 直接Append到对应文件块中
        // TODO: 文件流不支持Append操作处理逻辑
        if ($dataObj->AppendFile($handle, $cache, 0) === false) {
            throw new MFilesException(Yii::t('api',"The file upload error!"), MConst::HTTP_CODE_400);
        }
        
        fclose($handle);
        if (isset($this->_temp) && file_exists($this->_temp)) {
            @unlink($this->_temp);
        }

        // 检查文件上传是否完整，如果不完整，则返回错误
        $this->_size = $dataObj->size($cache);
        if ($this->_size > $size) {
            throw new MFilesException(Yii::t('api',"The file upload error!"), MConst::HTTP_CODE_400);
        } elseif ($this->_size < $size) {
            $this->ResponseRetryWith($hash, $size, $this->_size);
        }
        $this->signature = $hash;
        return $this->handleSave($hash, $cache);
    }
    
    /**
     * 使用post方法上传整文件
     */
    private function handleBreakPointFile($hash, $size, $offset) {
        $dataObj = Yii::app()->data;
        
        $cache = '/cache/' . MiniUtil::getPathBySplitStr($hash);
        // 文件不存在，则需要客户端全部重新传文件
        if ($dataObj->exists(dirname($cache)) === false) {
            $this->ResponseRetryWith($hash, $size, 0, FALSE);
        }

        // 如果收到的文件内容小于offset，那么需要要求客户端从received的位置开始上传
        $received = $dataObj->size($cache);
        if ($received < $offset) {
            $this->ResponseRetryWith($hash, $size, $received, FALSE);
        }
        
        $handle = $this->getInputFileHandle();
        // 直接Append到对应文件块中
        // TODO: 文件流不支持Append操作处理逻辑
        if ($dataObj->AppendFile($handle, $cache, $offset) === false) {
            throw new MFilesException(Yii::t('api',"The file upload error!"), MConst::HTTP_CODE_400);
        }
        
        fclose($handle);
        if (isset($this->_temp) && file_exists($this->_temp)) {
            @unlink($this->_temp);
        }
        $this->_size = $dataObj->size($cache);
        if ($this->_size > $size) {
            throw new MFilesException(Yii::t('api',"The file upload error!"), MConst::HTTP_CODE_400);
        } elseif ($this->_size < $size) {
            $this->ResponseRetryWith($hash, $size, $this->_size);
        }
        
        $this->signature = $hash;
        return $this->handleSave($hash, $cache);
    }
    
    /**
     * 
     * 写入文件,替换对应位置内容
     */
    private function handleWriteBytes($handle, $cache, $offset) {
        $dataObj = Yii::app()->data;

        $mode   = 'wb';
        if ($dataObj->exists($cache))
            $mode = 'r+b';

        $data = fread($handle, 8096);
        fseek($file, $offset);
        while ($data) {
            fwrite($file, $data);
            $data = fread($handle, 8096);
        }
        fclose($file);
        fclose($handle);
        clearstatcache();
        if (isset($this->_temp) && file_exists($this->_temp)) 
            @unlink($this->_temp);
    }
    
    /**
     * 
     * Enter description here ...
     */
    private function handleSave($hash, $tmp) {
        //data源处理对象
        $dataObj = Yii::app()->data;
        // 如果文件不存在则保存
        $store_path = MiniUtil::getPathBySplitStr($hash);
        if ($dataObj->exists($store_path) === false) {
            // 创建父目录
            if ($dataObj->exists(dirname($store_path)) === false) {
                MUtils::MkDirs(dirname($store_path));
            }
            
            if ($dataObj->move($tmp, $store_path, true) == false) {
                throw new MFilesException(Yii::t('api',"The file upload error!"), MConst::HTTP_CODE_400);
            }
        }
        return $store_path;
    }
    
    /**
     * 
     * 通知客户端，需要重新上传文件，从offset的位置开始
     */
    private function ResponseRetryWith($hash, $size, $offset, $success=TRUE) {
        $code = MConst::HTTP_CODE_449;
        if ($success)
            $code = MConst::HTTP_CODE_200;
        header('HTTP/1.1 ' . $code . ' ' . MConst::RETRY_WITH);
        header('ETag: ' . $hash);
        $response = array('hash' => $hash, 'offset' => $offset, 'size' => $size, 'success' => $success);
        echo json_encode($response);
        exit();
    }
    
    /**
     * 
     * 返回文件对象
     * @since 1.0.0
     */
    private function getInputFileHandle() {
        $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : NULL;
        $handle       = NULL;
        $tmp          = 'php://input';
        if (strpos($content_type, 'multipart/form-data; boundary=') !== FALSE){
            $tmp = $this->handleCheckFiles();
            if (file_exists(DOCUMENT_CACHE) == false)
                MUtils::MkDirsLocal(DOCUMENT_CACHE);
            $this->_temp = DOCUMENT_CACHE . basename($tmp);
            move_uploaded_file($tmp, $this->_temp);
            $tmp = $this->_temp;
        }
        $handle = fopen($tmp, 'rb');
        return $handle;
    }
    
    /**
     * 
     * 检查文件上传过程中是否存在错误
     * @since 1.0.0
     */
    private function handleCheckFiles() {
        if (count($_FILES) == 0) {
            throw new MFilesException(Yii::t('api',MConst::PARAMS_ERROR), MConst::HTTP_CODE_400);
        }
        
        $keys = array_keys($_FILES);
        if (count($keys) != 1) {
            throw new MFilesException(Yii::t('api',MConst::PARAMS_ERROR), MConst::HTTP_CODE_400);
        }
        $key = $keys[0];
        // 检查请求参数$_FILES
        if (isset($_FILES[$key]) === false)
        {
            throw new MFilesException(Yii::t('api',MConst::PARAMS_ERROR), MConst::HTTP_CODE_400);
        }
        // 检查文件上传过程是否有错
        if ($_FILES[$key]["error"] != 0) {
            throw new MFilesException(Yii::t('api',MConst::PARAMS_ERROR), MConst::HTTP_CODE_400);
        }
        return $_FILES[$key]["tmp_name"];
    }
    /**
     * put处理异常入口地址
     *
     */
    public function handleException($exception)
    {
        if (isset($this->_temp) && file_exists($this->_temp)) @unlink($this->_temp);
        parent::displayException($exception);
    }
}