<?php
/**
 * 权限业务处理类
 * @author app <app@miniyun.cn>
 * @link http://www.miniyun.cn
 * @copyright 2014 Chengdu MiniYun Technology Co. Ltd.
 * @license http://www.miniyun.cn/license.html
 * @since 1.6
 */
class PrivilegeBiz  extends MiniBiz{
    public $share_filter      = null;
    /**
     * 获得拥有权限的用户列表
     */
    public function getPrivilegeList($filePath){
        //获得绝对路径
        $privileges = MiniUserPrivilege::getInstance()->getPrivilegeList($filePath);
        $groupPrivileges = MiniGroupPrivilege::getInstance()->getPrivilegeList($filePath);
        $data = array();
        foreach($privileges as $item){
            $user = MiniUser::getInstance()->getUser($item['user_id']);
            $privilege = array();
            $privilege['id'] = $user['user_id'];
            $privilege['name'] = $user['user_name'];
            $privilege['nick'] = $user['nick'];
            $privilege['avatar'] = $user['avatar'];
            $permission = $item['permission'];
            $readValue = substr($permission,0,1);
            $modifyValue = substr($permission,1);
            if($readValue=='1'){//read权限 与js格式转化为一致
                $privilege['read'] = true;
            }else{
                $privilege['read'] = false;
            }
            if($modifyValue=='11111111'){
                $privilege['modified'] = true;
            }else{
                $privilege['modified'] = false;
            }
            $privilege['type'] = "0";
            array_push($data,$privilege);
        }
        foreach($groupPrivileges as $item){
            $group = MiniGroup::getInstance()->getById($item['group_id']);
            $privilege = array();
            $privilege['id']=$item['group_id'];
            $privilege['name']=$group['group_name'];
            $privilege['nick']=$group['group_name'];
            $privilege['avatar'] = MiniHttp::getMiniHost()."static/images/icon_group.png";
            $permission = $item['permission'];
            $readValue = substr($permission,0,1);
            $modifyValue = substr($permission,1);
            if($readValue=='1'){//read权限 与js格式转化为一致
                $privilege['read'] = true;
            }else{
                $privilege['read'] = false;
            }
            if($modifyValue=='11111111'){
                $privilege['modified'] = true;
            }else{
                $privilege['modified'] = false;
            }
            $privilege['type'] = "1";
            array_push($data,$privilege);
        }
        return $data;
    }
    /**
     * 保存用户权限
     * @param $filePath
     * @param $slaves
     * @return bool
     */
    public function save($filePath,$slaves){
        $device                   = MUserManager::getInstance()->getCurrentDevice();
        $userDeviceId             = $device["device_id"];
        $this->share_filter = MSharesFilter::init();
        //delete privilege
        $oldGroupPrivileges = MiniGroupPrivilege::getInstance()->getPrivilegeList($filePath);
        $oldUserPrivileges = MiniUserPrivilege::getInstance()->getPrivilegeList($filePath);
        //删除权限
        if(!empty($oldGroupPrivileges)){
            foreach($oldGroupPrivileges as $oldGroupPrivilege){
                $oldGroupId = $oldGroupPrivilege['group_id'];
                $groupExisted = false;
                foreach($slaves as $groupPrivilege){
                    $type = $groupPrivilege['type'];
                    if($type=='1'||$type=='2'){
                        $newGroupId =$groupPrivilege['id'];//todo 原先是($privilege['info']['slave_id'])，已修改(2)
                        if($newGroupId==$oldGroupId){
                            $groupExisted = true;
                        }
                    }
                }
                if($groupExisted==false){//todo 判断的地方有误 导致无法存入数据库(1) 见上(2)
                    MiniGroupPrivilege::getInstance()->deletePrivilege($oldGroupId,$filePath);
                }
            }
        }
        if (!empty($oldUserPrivileges)) {
            foreach ($oldUserPrivileges as $item) {
                $userId = $item['user_id']; //数据表中的userId
                $existed = false;
                foreach($slaves as $userPrivilege){
                    $type = $userPrivilege['type'];
                    if($type=='0'){
                        $newUserId =$userPrivilege['id'];//todo 原先是($privilege['info']['slave_id'])，已修改(2)
                        if($newUserId==$userId){
                            $existed = true;
                        }
                    }
                }
                if ($existed == false) {
                    MiniUserPrivilege::getInstance()->deletePrivilege($userId, $filePath);
                }
            }
        }
        $userIds = array();
        //创建权限
        foreach($slaves as $privilege){
            $permission = $privilege['privilege'];
            $privilegeType = $privilege['type'];
            if($privilegeType=='0'){
                MiniUserPrivilege::getInstance()->create($privilege['id'],$filePath,$permission);
                $userIds[] = $privilege['id'];

            }
            if($privilegeType=='1'){
                MiniGroupPrivilege::getInstance()->create($privilege['id'],$filePath,$permission);
                $groups = MiniUserGroupRelation::getInstance()->getByGroupId($privilege['id']);
                foreach($groups as $group){
                    $userIds[] = $group['user_id'];
                }
            }
            if($privilegeType=='2'){
                MiniGroupPrivilege::getInstance()->create($privilege['id'],$filePath,$permission);
            }
            $departmentPrivilege = new DepartmentPermissionBiz();
            $departmentPrivilege->getUserByDepartmentId($privilege['id']);
        }

        $ids = array_unique(array_merge($departmentPrivilege->ids,$userIds));
        foreach($ids as $id){
            $this->share_filter->slaves[$id] = $id;
        }
//        $meta_key = MConst::SHARED_FOLDERS;
//        $meta_value = MConst::SHARE_FOLDER;
//        MiniFileMeta::getInstance()->createFileMeta($filePath, $meta_key, $meta_value);
        //todo创建共享事件

        $event_action                    = MConst::SHARE_FOLDER;
        $ret_value                       = MiniEvent::getInstance()->createEvent(
            $this->user['id'],
            $userDeviceId,
            $event_action,
            $filePath,
            $filePath,
            MiniUtil::getEventRandomString(MConst::LEN_EVENT_UUID),
            $this->share_filter->type
        );
        if ($ret_value === false)
        {
            throw new MFileopsException(
                Yii::t('api','Internal Server Error'),
                MConst::HTTP_CODE_500);
        }
        $this->share_filter->is_shared = true;
        // 为每个共享用户创建事件
        $this->share_filter->handlerAction($event_action, $userDeviceId, $filePath, $filePath);
        /**
         * 存储权限之后更新被分享文件的file_type = 2，出现分享图标
         */
        $fileValue = array('updated_at'=>time());
        MiniFile::getInstance()->updateByPath($filePath,$fileValue);
        MiniFile::getInstance()->togetherShareFile($filePath, MConst::OBJECT_TYPE_SHARED);
        return array('success'=>true);
    }
    /**
     * 根据file_path查询文件权限
     */
    public function get($filePath){
        $filePath = MiniUtil::getAbsolutePath($this->user["id"],$filePath);
        $privilege = MiniUserPrivilege::getInstance()->getFolderPrivilege($filePath);
        return $privilege;
    }
    public function getSlaveIdsByPath($filePath){
        $slaveIds = array();
        $users = MiniUserPrivilege::getInstance()->getPrivilegeList($filePath);
        $fileItem = explode('/',$filePath);
        $slaveIds[] = $fileItem[1];
        if(count($users)>0){
            foreach($users as $user){
                    $slaveIds[] = $user['user_id'];
            }
        }
        $groups = MiniGroupPrivilege::getInstance()->getPrivilegeList($filePath);
        $departmentPrivilege = new DepartmentPermissionBiz();
        foreach($groups as $group){
            $departmentPrivilege->getUserByDepartmentId($group['group_id']);
        }
        $ids =  array_unique(array_merge($departmentPrivilege->ids,$slaveIds));
        $userIds = array();
        foreach($ids as $id){
            if($id!=$this->user['id']){
                $userIds[$id] = $id;
            }
        }
       return $userIds;
    }
    /**
     * 取消共享，删除权限
     */
    public function delete($filePath){
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
        $this->share_filter = MSharesFilter::init();
        $device                   = MUserManager::getInstance()->getCurrentDevice();
        $userDeviceId             = $device["device_id"];
        $this->share_filter->slaves = $this->getSlaveIdsByPath($filePath);
        MiniUserPrivilege::getInstance()->deleteByFilePath($filePath);
        MiniGroupPrivilege::getInstance()->deleteByFilePath($filePath);
        MiniFile::getInstance()->cancelPublic($filePath);
        $event_action                    = MConst::CANCEL_SHARED;
        $ret_value                       = MiniEvent::getInstance()->createEvent(
            $this->user['id'],
            $userDeviceId,
            $event_action,
            $filePath,
            $filePath,
            MiniUtil::getEventRandomString(MConst::LEN_EVENT_UUID),
            $this->share_filter->type
        );
        $this->share_filter->is_shared = true;
        if ($ret_value === false)
        {
            throw new MFileopsException(
                Yii::t('api','Internal Server Error'),
                MConst::HTTP_CODE_500);
        }

        // 为每个共享用户创建事件
        $this->share_filter->handlerAction($event_action, $userDeviceId, $filePath, $filePath);
        return true;
    }
    /**
     * 获取共享文件的根目录文件
     */
    public function getSharedParentPath($sharedpath){
        $arr = explode('/',$sharedpath);
        $parentPath = "/".$arr[1];
        for($i=2;$i<count($arr);$i++){
            $parentPath = $parentPath."/".$arr[$i];
            $file = MiniFile::getInstance()->getByFilePath($parentPath);
            if($file['file_type']==2){
                return $parentPath;
            }
        }
        return null;
    }

    /**
     * 用户对应某个文件的权限
     * @param $sharedPath
     */
    public function getUserPermission($sharedPath){
        $userId = $this->user['id'];
    }
}