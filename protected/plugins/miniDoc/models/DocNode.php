<?php
/**
 *      [迷你云] (C)2009-2012 南京恒为网络科技.
 *   软件仅供研究与学习使用，如需商用，请访问www.miniyun.cn获得授权
 * 
 */
?>
<?php

class DocNode extends CMiniyunModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }
    public function tableName(){
        return Yii::app()->params['tablePrefix'].'doc_nodes';
    }

}