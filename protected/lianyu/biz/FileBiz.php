<?php
/**
 * 文件业务处理类
 * @author app <app@miniyun.cn>
 * @link http://www.miniyun.cn
 * @copyright 2014 Chengdu MiniYun Technology Co. Ltd.
 * @license http://www.miniyun.cn/license.html
 * @since 1.6
 */
class FileBiz  extends MiniBiz{

    /**
     * download current user file
     * @param $path
     * @return mixed
     */
    public function download($path){
        $share = new MiniShare();
        $minFileMeta = $share->getMinFileMetaByPath($path);
        MiniFile::getInstance()->download($minFileMeta['ori_path']);
    }

    /**
     * 目录打包下载
     */
    public function downloadToPackage($paths,$filePath){
        $arr = explode('/',$filePath);
        $isRoot = false;
        $isMine = false;
        if(count($arr)==3){
            $isRoot = true;
        }
        $fileOwnerId = $arr[1];
        $currentUser = $this->user;
        $currentUserId = $currentUser['user_id'];
        if($fileOwnerId==$currentUserId ){
            $isMine = true;
        }
        if($isRoot&&!$isMine){//如果是在根目录下且不是自己的目录 则后台控制不准取消共享
            throw new MFileopsException(
                Yii::t('api','Internal Server Error'),
                MConst::HTTP_CODE_409);
        }
        //打包下载限制
        header("Content-type: text/html; charset=utf-8");
        $limit = new DownloadPackageLimit();
        $limitCount = $limit->getLimitCount();
        $limitSize  = $limit->getLimitSize();
        $code = '';
        $fileNames = array();
        $user = $this->user;
        $userId = $user['user_id'];
        $paths = explode(',',$paths);
        foreach($paths as $path){
            $file = MiniFile::getInstance()->getByPath($path);
            if (empty($file)){
                echo  Yii::t('i18n','error_path');
                Yii::app()->end();
            }
            $code = $code.','.$file['id'] ;
            array_push($fileNames,$file['file_name']);
        }

        if(count($fileNames)>1){
            $packageName = 'miniyun';
        }else{
            $packageName = $fileNames[0];
        }

        //创建临时文件夹
        $fileSystem = new CFileSystem();
        MUtils::MkDirsLocal(DOCUMENT_TEMP.$userId);
        $storePath = DOCUMENT_TEMP.$userId."/".$packageName;
        $array = array();
        $ids = explode(",", $code);
        foreach ($ids as $id){
            $file = MiniFile::getInstance()->getById($id);
            if (empty($file)){
                continue;
            }
            if ($file["file_type"] == MConst::OBJECT_TYPE_FILE){    //属于自己的文件
                $array[] = $file;
            } else { //不属于自己的文件
                //查询共有多少个子目录
                $array[] = $file;
                $files = MiniFile::getInstance()->getChildrenByPath($file["file_path"]);
                $array = array_merge($array, $files);
            }
        }
        if (count($array) > $limitCount){
            echo  Yii::t('i18n','out_of_count');
            Yii::app()->end();
        }

        $size = $this->calculateSize($array);
        if ($size > $limitSize*1024*1024){
            echo  Yii::t('i18n','out_of_size');
            Yii::app()->end();
        }

        $path         = CUtils::removeUserFromPath($array[0]["file_path"]);
        $removeParent = pathinfo($path, PATHINFO_DIRNAME);
        if (strlen($removeParent) == 1){
            $removeParent = "";
        }
        //zip压缩
        $zip = new ZipArchive;
        $zipFile = $storePath.".zip";
        //删除上次存在的压缩文件
        $fileSystem->delete($zipFile);
        try {
            $zipFile        = mb_convert_encoding($zipFile, "gb2312", "UTF-8");
        } catch (Exception $e) {
            $zipFile        = $zipFile;
        }
        if ($zip->open($zipFile,ZIPARCHIVE::OVERWRITE) === TRUE) {
            //执行拷贝操作
            foreach ($array as $file){
                $fileType = $file["file_type"];
                $filePath = $file["file_path"];
                //获取存储文件的绝对路径
                if (!empty($removeParent)){
                    $relativePath = CUtils::str_replace_once($removeParent,"",CUtils::removeUserFromPath($filePath));
                } else {
                    $relativePath = CUtils::removeUserFromPath($filePath);
                }
                //打包加上nick
                $relativePath = $packageName. $relativePath;
                //转换文件编码为中文编码
                try {
                    $store        = mb_convert_encoding($relativePath, "gb2312", "UTF-8");
                } catch (Exception $e) {
                    $store        = $relativePath;
                }
                $hasRead = true;
                if ($userId == $file["user_id"] && $fileType == MConst::OBJECT_TYPE_FILE){    //属于自己的文件
                    $this->addToFile($zip, $file, $store, $fileSystem);
                } elseif ($userId != $file["user_id"] && $fileType == MConst::OBJECT_TYPE_FILE){ //不属于自己的文件
                    if ($hasRead){
                        $this->addToFile($zip, $file, $store, $fileSystem);
                    }
                } elseif ($userId == $file["user_id"] && $fileType == MConst::OBJECT_TYPE_DIRECTORY){ //属于自己的文件夹
                    $this->addToFolder($zip, $store);
                } else { //不属于自己的文件夹
                    if ($hasRead){
                        $this->addToFolder($zip, $store);
                    }
                }
            }
            $zip->close(); //关闭
        }
        if (!file_exists($zipFile)){
            echo  Yii::t('i18n','no_privilege');
            Yii::app()->end();
        };
        //进行下载
        CUtils::output($zipFile, "application/octet-stream", $packageName.".zip");
    }

    /**
     *
     * 将文件拷贝到临时目录
     *
     * @since 1.0.0
     */
    private function calculateSize($files){
        $size = 0;
        foreach ($files as $file){
            if ($file["file_type"] == MConst::OBJECT_TYPE_FILE){
                $size += $file["file_size"];
            }
        }
        return $size;
    }

    /**
     *
     * 将文件夹添加到临时目录
     *
     * @since 1.0.0
     */
    private function addToFolder($zip, $storePath){
        $zip->addEmptyDir($storePath);
    }

    /**
     *
     * 将文件拷贝到临时目录
     *
     * @since 1.0.0
     */
    private function addToFile($zip, $file, $storePath){
        $fileVersion =  MiniVersion::getInstance()->getVersion($file["version_id"]);
        $basePath  = MiniUtil::getPathBySplitStr ($fileVersion["file_signature"]);

        $dataObj = Yii::app()->data;
        $contents = $dataObj->get_contents($basePath);
        $zip->addFromString($storePath, $contents);
    }
    /**
     * 通过signature下载文件
     * @param $signature .文件signature
     * @param $filePath .文件路径
     */
    public function downloadBySignature($filePath,$signature){
        $item = explode("/",$filePath);
        $permissionModel = new UserPermissionBiz($filePath,$this->user['id']);
        $permissionArr = $permissionModel->getPermission($filePath,$this->user['id']);
        if($item[1]!==$this->user['id']&&count($permissionArr)==0){
            throw new MFilesException(Yii::t('api',MConst::PARAMS_ERROR), MConst::HTTP_CODE_400);
        }
        $this->content($filePath,$signature,true);
    }

    /**
     * download current user file
     * 通过signature查询文件
     * @param $filePath
     * @param $signature
     * @param $forceDownload
     * @return mixed
     */
    public function content($filePath,$signature,$forceDownload=false){
        $share = new MiniShare();
        $miniFileMeta = $share->getMinFileMetaByPath($filePath);
        if($miniFileMeta!==NULL){
            MiniFile::getInstance()->getContent($miniFileMeta['ori_path'],$signature,null,$forceDownload);
        }
    }

    /**
     * 獲取文本文件信息！
     * @param $path
     * @param $signature
     * @return mixed
     */
    public function txtContent($path,$signature){
        $share = new MiniShare();
        $minFileMeta = $share->getMinFileMetaByPath($path);
        $file = array();
        $content = MiniFile::getInstance()->getTxtContent($minFileMeta['ori_path'],$signature);
        $file['content'] = $content;
        $file['type']    = $minFileMeta['mime_type'];
        return $file;
    }

    /**
     * 獲取office文件預覽地址
     * @param $path
     * @param $signature
     * @return string
     */
    public function doc($path,$signature){
        $share = new MiniShare();
        $minFileMeta = $share->getMinFileMetaByPath($path);
        $type     = explode('/',$minFileMeta['mime_type']);
        $fileType = '';
        if($type[1] == 'msexcel'){
            $fileType = 'xls';
        }else if($type[1] == 'msword'){
            $fileType = 'doc';
        }else if($type[1] == 'mspowerpoint'){
            $fileType = 'ppt';
        }else if($type[1] == 'zip'){
            $fileType = 'zip';
        }else if($type[1] == 'x-rar-compressed'){
            $fileType = 'rar';
        }
        $isSupport = apply_filters("is_support_doc");
        if($isSupport){
            $url = Yii::app()->getBaseUrl().'miniDoc/viewer/'. $fileType.'?path='.$path;
        }else{
            $url = "";
        }
        return $url;
    }
    /**
     * 上传文件
     */
    public function upload($path){
        //下面的方式将取得共享目录下的原始路径，如在自己目录下，会返回当前用户目录
//        $share = new MiniShare();
//        $minFileMeta = $share->getMinFileMetaByPath($path);
        //表示没有权限
//        if($minFileMeta===NULL){
//            throw new MFilesException(Yii::t('api',MConst::PARAMS_ERROR), MConst::HTTP_CODE_400);
//            return;
//        }
//        $filePath = $minFileMeta["ori_path"];
        $fileHandler = new MFilePostController();
        $uri  = '/files/miniyun' . $path;
        $fileHandler->invoke($uri);
    }

    /**
     * 获取已分享的文件
     * @param $path
     * @return bool
     */
    public  function shared($path){
        $userId = $this->user['id'];
        $absolutePath = MiniUtil::getAbsolutePath($userId,$path);
        $file = MiniFile::getInstance()->getByPath($absolutePath);
        $link = MiniLink::getInstance()->getByFileId($file['id']);
        if(empty($link['share_key'])){
            return false;
        }else{
            return true;
        }
    }
    /**
     * 清空回收站
     */
    public function cleanRecycle(){
        $user = $this->user;
        $files = MiniFile::getInstance()->getUserRecycleFile($user['user_id']);
        foreach($files as $file){
            MiniFile::getInstance()->deleteFile($file['id']);
        }
        if(MiniFile::getInstance()->trashCount($user['user_id']) == 0){
            return array('success'=>true);
        }else{
            return array('success'=>false);
        }
    }
    /**
     * extend
     */
    public function getExtendTactics(){
        $editors = MiniOption::getInstance()-> getOptionValue('online_editor');
        $editors = unserialize($editors);
        return $editors;
    }
}

