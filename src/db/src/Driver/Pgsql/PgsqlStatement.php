<?php
/**
 * This file is part of Swoft.
 *
 * @link     https://swoft.org
 * @document https://doc.swoft.org
 * @contact  group@swoft.org
 * @license  https://github.com/swoft-cloud/swoft/blob/master/LICENSE
 */
namespace Swoft\Db\Driver\Pgsql;

use Swoft\Db\Driver\Driver;
use Swoft\Db\Bean\Annotation\Statement;

/**
 * PgsqlStatement
 * @Statement(driver=Driver::PGSQL)
 */
class PgsqlStatement extends \Swoft\Db\Statement
{
    /**
     * @var string
     */
    private $profilePrefix = "pgsql";

    /**
     * @return ResultInterface
     */
    public function execute()
    {
        if (App::isCoContext()) {
            return $this->getCorResult();
        }
        return $this->getSyncResult();
    }

    /**
     * @param string $sql
     *
     * @return array
     */
    private function getSqlIdAndProfileKey(string $sql)
    {
        $sqlId      = md5($sql);
        $profileKey = sprintf('%s.%s', $sqlId, $this->profilePrefix);

        return [$sqlId, $profileKey];
    }

    /**
     * 转换结果
     *
     * @param AbstractDbConnection $connection
     * @param mixed $result
     *
     * @return mixed
     */
    private function transferResult(AbstractDbConnection $connection, $result)
    {
        $isFindOne        = isset($this->limit['limit']) && $this->limit['limit'] === 1;
        $isUpdateOrDelete = $this->isDelete() || $this->isUpdate();
        if ($result !== false && $this->isInsert()) {
            $result = $connection->getInsertId();
        } elseif ($result !== false && $isUpdateOrDelete) {
            $result = $connection->getAffectedRows();
        } elseif ($isFindOne && $result !== false && $this->isSelect()) {
            $result = $result[0] ?? [];
        }

        return $result;
    }

    /**
     * insert语句
     *
     * @return string
     */
    protected function getInsertStatement(): string
    {
        $statement = '';
        if (!$this->isInsert()) {
            return $statement;
        }

        // insert语句
        $statement .= $this->getInsertString();

        // set语句
        if ($this->set) {
            foreach ($this->set as $set) {
                $keys[]   = $set['column'];
                $values[] = $this->getQuoteValue($set['value']);
            }
            $statement .= ' (' . implode(',', $keys) . ')' . ' VALUES (' . implode(',', $values) . ');';
        }

        return $statement;
    }

    /**
     * 字符串转换
     *
     * @param $value
     * @return string
     */
    protected function getQuoteValue($value): string
    {
        if (\is_string($value)) {
            $value = "'" . $value . "'";
        }
        return $value;
    }

    /**
     * insert表
     *
     * @return string
     */
    protected function getInsert(): string
    {
        return ' INTO "' . $this->insert . '"';
    }

    /**
     * update表
     *
     * @return mixed
     */
    protected function getUpdate()
    {
        return '"' . $this->update . '"';
    }

    /**
     * @param mixed $key
     * @return string
     */
    protected function formatParamsKey($key): string
    {
        if (\is_string($key)) {
            return ':' . $key;
        }
        if (App::isWorkerStatus()) {
            return '?' . $key;
        }

        return $key;
    }

    /**
     * from表
     *
     * @return string
     */
    protected function getFrom(): string
    {
        $table = $this->from['table']??'';

        return '"' . $table . '"';
    }

    /**
     * select语句
     *
     * @return string
     */
    protected function getSelectString(): string
    {
        $statement = '';
        if (empty($this->select)) {
            return $statement;
        }

        // 字段组拼
        foreach ($this->select as $column => $alias) {
            $column = explode(',',$column);
            $column =  array_map(function($v){
                return '"'.$v.'"';
            },$column);

            $statement .=  implode(',',$column);
            if ($alias !== null) {
                $statement .= ' AS ' . $alias;
            }
            $statement .= ', ';
        }

        //select组拼
        $statement = substr($statement, 0, -2);
        if (!empty($statement)) {
            $statement = 'SELECT ' . $statement;
        }

        return $statement;
    }
}
