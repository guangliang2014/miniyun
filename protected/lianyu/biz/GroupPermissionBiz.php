<?php
/**
 * 群组权限控制
 * @author app <app@miniyun.cn>
 * @link http://www.miniyun.cn
 * @copyright 2014 Chengdu MiniYun Technology Co. Ltd.
 * @license http://www.miniyun.cn/license.html 
 * @since 1.7
 */
class GroupPermissionBiz extends MiniBiz{
    public $authority;
    public function GroupPermissionBiz($path,$userId){
        $this->getPermission($path,$userId);
    }
    public function getPermission($path,$userId){
        $privilegeLength = 9;//权限长度 后期更改则做相应调整
        $userInGroups = MiniUserGroupRelation::getInstance()->getByUserId($userId);//用户所在的部门列表，查表user_group_relation
        if(count($userInGroups)>0){//说明该用户被其他用户分配到其群组中
            //寻找该用户所在的组有无权限，有权限则说明对应的权限有共享文件
            $privilegeArr = array();//一个用户同一个被共享文件对应多个群组权限集合
            foreach($userInGroups as $userInGroup){
                $groupId = $userInGroup['group_id'];
                $group = MiniGroup::getInstance()->getById($groupId);
                if($group['user_id']!=-1){//表示该组为群组
                    $groupPrivilege = MiniGroupPrivilege::getInstance()->getSpecifyPrivilege($groupId, $path);
                    if(!empty($groupPrivilege)){
                        array_push($privilegeArr,$groupPrivilege);
                    }
                }
            }
            if(count($privilegeArr)>0){
                $permission = '';
                for($i=0;$i<$privilegeLength;$i++){
                    foreach($privilegeArr as $privilege){
                        $can = false;
                        $value = substr($privilege['permission'],$i,1);
                        if($value == '1'){
                            $can = true;
                            break;
                        }
                    }
                    if($can){
                        $permission .='1';
                    }else{
                        $permission .='0';
                    }
                }
                return
                    $this->authority = $permission;
//                        array('success'=>true,'permission'=>$permission);
            }else{
                return
                    $this->authority = null;
//                        array('success'=>false,'msg'=>'file is not shared by group');
            }
        }else{
            $this->authority = null;
//                array('success'=>false,'msg'=>'user is not in a group');
        }
//        return new MiniPermission();
    }
}