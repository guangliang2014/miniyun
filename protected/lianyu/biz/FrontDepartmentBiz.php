<?php
/**
 * 部门权限
 * @author app <app@miniyun.cn>
 * @link http://www.miniyun.cn
 * @copyright 2014 Chengdu MiniYun Technology Co. Ltd.
 * @license http://www.miniyun.cn/license.html 
 * @since 1.7
 */
class FrontDepartmentBiz extends MiniBiz{
    /**
     * 群组列表
     */
    public function getList(){
        $data = MiniGroup::getInstance()->getTreeNodes(-1);
        return $data;
    }
    /**
     * 用户与群组关联关系列表
     */
    public function usersGroupRelation($currentPage,$pageSize){
        $user = $this->user;
        $userId = $user['user_id'];
        $result = MiniGroup::getInstance()->getList($userId);
        $groupList = $result['list'];
        foreach($groupList as $item){
            $groupId = $item['id'];
            $relatedList = MiniUserGroupRelation::getInstance()->getPageList($groupId,$currentPage,$pageSize);
            $relatedUserList = $relatedList['list'];
            var_dump($relatedUserList);
        }
    }
    /**
     * 新建群组
     */
    public function create($groupName){
        $user = $this->user;
        $userId = $user['user_id'];
        return MiniGroup::getInstance()->create($groupName,$userId);
    }
    /**
     * 删除群组
     */
    public function delete($groupName){
        $user = $this->user;
        $userId = $user['user_id'];
        return MiniGroup::getInstance()->delete($groupName,$userId);
    }
    /**
     * 群组更名
     */
    public function rename($oldGroupName,$newGroupName){
        $user = $this->user;
        $userId = $user['user_id'];
        return MiniGroup::getInstance()->rename($oldGroupName,$newGroupName,$userId);
    }
    /**
     * 群组下的用户列表
     */
    public function userList($groupId){
        $items = MiniUserGroupRelation::getInstance()->getList($groupId);
        if($items['success']==true){
            $list = $items['list'];
            $userList = array();
            foreach($list as $item){
                $arr = array();
                $user = MiniUser::getInstance()->getUser($item['user_id']);
                $arr['id']=$item['user_id'];
                $arr['name']=$user['user_name'];
                $arr['nick']=$user['nick'];
                $arr['avatar']=$user['avatar'];
                array_push($userList,$arr);
            }
            $items['list']=$userList;
            return $items;
        }else{
            return $items;
        }
    }
    /**
     * 绑定用户到群组
     */
    public function bind($userId,$groupId){
        return MiniUserGroupRelation::getInstance()->bind($userId,$groupId);
    }
    /**
     * 用户与群组解绑
     */
    public function unbind($userId,$groupId){
        return MiniUserGroupRelation::getInstance()->unbind($userId,$groupId);
    }
    /**
     * 搜索群组
     */
    public function search(){

    }
}