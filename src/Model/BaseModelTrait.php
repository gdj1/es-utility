<?php

namespace WonderGame\EsUtility\Model;

use EasySwoole\ORM\AbstractModel;
use EasySwoole\ORM\DbManager;
use EasySwoole\RedisPool\RedisPool;

/**
 * @extends AbstractModel
 */
trait BaseModelTrait
{
    protected $gameid = '';

    protected $sort = ['id' => 'desc'];

    // 编辑提交时 extension字段的处理方式： merge-合并；replace-覆盖
    protected $_extSave = 'replace';

    public function __construct($data = [], $tabname = '', $gameid = '')
    {
        // $tabname > $this->tableName > $this->_getTable()
        $tabname && $this->tableName = $tabname;
        if ( ! $this->tableName) {
            $this->tableName = $this->_getTable();
        }

        $this->gameid = $gameid;

//        $this->autoTimeStamp = false;
        $this->createTime = 'instime';
        $this->updateTime = false;
        $this->setBaseTraitProtected();

        parent::__construct($data);
    }

    protected function setBaseTraitProtected()
    {
    }

    /**
     * 获取表名，并将将Java风格转换为C的风格
     * @return string
     */
    protected function _getTable()
    {
        $name = basename(str_replace('\\', '/', get_called_class()));
        return parse_name($name);
    }

    public function getPk()
    {
        /* @var AbstractModel $this */
        return $this->schemaInfo()->getPkFiledName();
    }

    protected function getExtensionAttr($extension = '', $alldata = [])
    {
        return is_array($extension) ? $extension : json_decode($extension, true);
    }

    /**
     * 数据写入前对extension字段的值进行处理
     * @access protected
     * @param array $extension 原数据
     * @return string|array 处理后的值
     */
    protected function setExtensionAttr($extension = [])
    {
        // QueryBuilder::func 等结构
        if (is_array($extension) && in_array(array_key_first($extension), ['[I]', '[F]', '[N]'])) {
            return $extension;
        }

        $ext = [];

        /*
         * 如果需要合并extension，需要在模型内部的setBaseTraitProtected方法中将extSave属性改为merge
         * 或者在实例化模型后，外部调用extSave('merge')方法
         */
        /* @var AbstractModel $this */
        if ($this->_extSave == 'merge') {
            $pk = $this->schemaInfo()->getPkFiledName();
            // 现有数据
            $ext = $this->toArray()['extension'] ?? [];
            $ext or $ext = [];
        }

        if (is_string($extension)) {
            $extension = json_decode($extension, true);
            if ( ! $extension) {
                return json_encode(new \stdClass());
            }
        }
        return json_encode(array_merge_multi($ext, $extension));
    }

    protected function setInstimeAttr($instime, $all)
    {
        return is_numeric($instime) ? $instime : strtotime($instime);
    }

    public function scopeIndex()
    {
        return $this;
    }

    public function setOrder(array $order = [])
    {
        $sort = $this->sort;
        // 'id desc'
        if (is_string($sort)) {
            list($sortField, $sortValue) = explode(' ', $sort);
            $order[$sortField] = $sortValue;
        } // ['sort' => 'desc'] || ['sort' => 'desc', 'id' => 'asc']
        else if (is_array($sort)) {
            // 保证传值的最高优先级
            foreach ($sort as $k => $v) {
                if ( ! isset($order[$k])) {
                    $order[$k] = $v;
                }
            }
        }
        /* @var AbstractModel $this */
        foreach ($order as $key => $value) {
            $this->order($key, $value);
        }
        return $this;
    }

    /**
     * 不修改配置的情况下，all结果集转Collection，文档： http://www.easyswoole.com/Components/Orm/toArray.html
     * @param bool $toArray
     * @return array|bool|\EasySwoole\ORM\Collection\Collection|\EasySwoole\ORM\Db\Cursor|\EasySwoole\ORM\Db\CursorInterface
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function ormToCollection($toArray = true)
    {
        /* @var AbstractModel $this */
        $result = $this->all();
        if ( ! $result instanceof \EasySwoole\ORM\Collection\Collection) {
            $result = new \EasySwoole\ORM\Collection\Collection($result);
        }
        return $toArray ? $result->toArray() : $result;
    }

    /**
     * 删除rediskey
     * @param mixed ...$key
     */
    public function delRedisKey(...$key)
    {
        $redis = RedisPool::defer();
        $redis->del($key);
    }

    // 开启事务
    public function startTrans()
    {
        /* @var AbstractModel $this */
        DbManager::getInstance()->startTransaction($this->getQueryConnection());
    }

    public function commit()
    {
        /* @var AbstractModel $this */
        DbManager::getInstance()->commit($this->getQueryConnection());

    }

    public function rollback()
    {
        /* @var AbstractModel $this */
        DbManager::getInstance()->rollback($this->getQueryConnection());
    }

    // 为避免与属性修改器冲突，特意不以get或set开头
    public function extSave($way = null)
    {
        if ( ! $way) {
            return $this->_extSave;
        }

        in_array($way, ['merge', 'replace']) && $this->_extSave = $way;
        return $this;
    }
}
