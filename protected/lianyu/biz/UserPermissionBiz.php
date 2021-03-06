<?php
/**
 * 用户权限控制
 * @author app <app@miniyun.cn>
 * @link http://www.miniyun.cn
 * @copyright 2014 Chengdu MiniYun Technology Co. Ltd.
 * @license http://www.miniyun.cn/license.html 
 * @since 1.7
 */
class UserPermissionBiz extends MiniBiz{
    private $shareRootPath;
    private $shareUserNick;
    public  $authority;
    public function  UserPermissionBiz($path,$userId){
        $file = MiniFile::getInstance()->getByPath($path);
        if(empty($file)){
            throw new MFilesException(Yii::t('api',MConst::PARAMS_ERROR), MConst::HTTP_CODE_400);
        }
        $this->getPermission($path,$userId);
    }
    public function getPermission($path,$userId){
        //查询公共目录
        $pathArr = explode('/',$path);
        $masterId = $pathArr[1];
        $master = MiniUser::getInstance()->getUser($masterId);
        $shareUserNick = $master['nick'];
        $privilegeLength = 9;
        $file = MiniFile::getInstance()->getByPath($path);
        $fileType = $file['file_type'];
        if($fileType==2){//如果刚好是共享目录
            if((int)$masterId!=$userId){//该共享目录非当前用户目录时才会涉及权限
                $userPrivilege = MiniUserPrivilege::getInstance()->getSpecifyPrivilege($userId,$path);
                if(empty($userPrivilege)){//如果不存在user_privilege，则向上查找group_privilege和department_privilege
                    $groupPrivilege = new GroupPermissionBiz($path,$userId);
                    $groupPermission = $groupPrivilege->authority;
                    $departmentPrivilege = new DepartmentPermissionBiz();
                    $departmentPermission = $departmentPrivilege->getPermission($userId,$path);
                    if(empty($groupPermission)){
                        $permission = $departmentPermission;
                    }
                    if(empty($departmentPermission)){
                        $permission = $groupPermission;
                    }
                    if(!empty($groupPermission)&&!empty($departmentPermission)){
                        $permission = '';
                        $total = $groupPermission+$departmentPermission;
                        for($i=0;$i<$privilegeLength;$i++){
                            $value = substr($total,$i,1);
                            if($value == '1'||$value == '2'){
                                $permission .='1';
                            }else{
                                $permission .='0';
                            }
                        }
                    }
                    if(empty($groupPermission)&&empty($departmentPermission)){
                        $permission = null;
                    }
                }else{
                    $permission = $userPrivilege['permission'];
                }
                if($permission==null){
                    return $this->authority = array('permission'=>$permission);
                }
                return $this->authority = array("permission"=>$permission,"share_root_path"=>$path,"share_user_nick"=>$shareUserNick,"is_share_folder"=>true,'can_set_share'=>0);
            }
            return $this->authority = array("permission"=>MConst::SUPREME_PERMISSION,"share_root_path"=>$path,"share_user_nick"=>$shareUserNick,'can_set_share'=>1);
        }
        if($fileType==1||$fileType==0){//普通目录情况
            $model = new GeneralFolderPermissionBiz($path);
//            if($model->permission == null){
                if($model->isChildrenShared($path)){
                    $permission = MConst::SUPREME_PERMISSION;
                    return $this->authority = array("permission"=>$permission,"share_user_nick"=>$shareUserNick,'children_shared'=>true,'can_set_share'=>0);
                }
//                return $this->authority = null;
//            }
            if($model->isShared){//如果该普通目录向上或者向下有共享
                if($model->isParentShared($path)){//如果是父目录被共享
                    if((int)$masterId!=$userId){//非共享者本人操作此文件
                        $permission = $model->permission;
                        return $this->authority = array("permission"=>$permission,"share_root_path"=>$model->shareRootPath,"share_user_nick"=>$shareUserNick,"is_share_folder"=>true,'can_set_share'=>0);
                    }else{//本人操作文件
                        $permission = MConst::SUPREME_PERMISSION;
                        return $this->authority = array("permission"=>$permission,"share_root_path"=>$model->shareRootPath,"share_user_nick"=>$shareUserNick,"is_share_folder"=>true,'can_set_share'=>0);
                    }
                }
            }else{//向上向下均没有共享
                return $this->authority = null;
            }
        }
        if($fileType==4){//公共目录情况
            $model = new PublicFolderPermissionBiz();
            $permission = $model->getPublicPermission($path);
            if($permission == null){
                return $this->authority = null;
            }
            if((int)$masterId!=$userId){//非共享者本人操作此文件
                return $this->authority = array("permission"=>$permission,"share_user_nick"=>$shareUserNick,"is_public_folder"=>true,'can_set_share'=>0);
            }else{
                $permission = MConst::SUPREME_PERMISSION;
                return $this->authority = array("permission"=>$permission,"share_user_nick"=>$shareUserNick,"is_public_folder"=>true,'can_set_share'=>0);
            }
        }
    }
}