<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */
namespace TNW\Salesforce\Synchronize\Transport\Calls\Query;

use RuntimeException;
use SplObjectStorage;

class Input extends SplObjectStorage
{
    /**
     * @var string
     */
    public $from;

    /**
     * @var array
     */
    public $columns;

    /**
     * @var array
     */
    protected $conditions = [];

    /**
     * @param array $entities
     * @return string
     */
    public function query(array $entities = [])
    {
        if (empty($this->from)) {
            throw new RuntimeException('SOQL part "from" is Empty');
        }

        if (empty($this->columns)) {
            throw new RuntimeException('SOQL part "columns" is Empty');
        }

        if (empty($entities)) {
            $entities = iterator_to_array($this);
        }

        return sprintf('SELECT %s FROM %s WHERE %s ORDER BY CreatedDate DESC', $this->select($this->columns), $this->from, $this->where($entities));
    }

    /**
     * @param array $columns
     * @return string
     */
    public function select(array $columns)
    {
        return implode(', ', $columns);
    }

    /**
     * @param array $entities
     * @return string
     */
    public function where(array $entities)
    {
        $groups = $this->mergeGroup($entities);

        $this->prepareLookupWhereGroup($groups);
        return $this->generateLookupWhereGroup($groups);
    }

    /**
     * @param $entities
     * @return array
     */
    protected function mergeGroup(array $entities)
    {
        $group = [];
        foreach ($entities as $entity) {
            $group['OR'][] = $this->offsetGet($entity);
        }

        return $group;
    }

    /**
     * @param array $groups
     */
    protected function prepareLookupWhereGroup(array &$groups)
    {
        foreach ($groups as &$group) {
            foreach ($group as $fieldName => &$condition) {
                switch (true) {
                    case array_key_exists('=', $condition):
                        $value = $this->soqlQuote($condition['=']);
                        $condition = "$fieldName={$value}";
                        break;

                    case array_key_exists('!=', $condition):
                        $value = $this->soqlQuote($condition['!=']);
                        $condition = "$fieldName!={$value}";
                        break;

                    case array_key_exists('LIKE', $condition):
                        $value = $this->soqlQuote($condition['LIKE']);
                        $condition = "$fieldName LIKE {$value}";
                        break;

                    case array_key_exists('IN', $condition):
                        $in = is_array($condition['IN'])
                            ? implode(',', array_map([$this, 'soqlQuote'], array_unique($condition['IN'])))
                            : $condition['IN'];

                        $condition = "$fieldName IN ({$in})";
                        break;

                    case array_key_exists('NOT IN', $condition):
                        $in = is_array($condition['NOT IN'])
                            ? implode(',', array_map([$this, 'soqlQuote'], array_unique($condition['NOT IN'])))
                            : $condition['NOT IN'];

                        $condition = "$fieldName NOT IN ({$in})";
                        break;

                    default:
                        if (is_array($condition)) {
                            $this->prepareLookupWhereGroup($condition);
                        }
                        break;
                }
            }
        }
    }

    /**
     * @param $value
     * @return string
     */
    protected function soqlQuote($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        } else {
            $value = (string)$value;
        }

        $value = addslashes($value);
        return "'$value'";
    }

    /**
     * @param array $groups
     * @return string
     */
    protected function generateLookupWhereGroup(array $groups)
    {
        $sql = '';
        $first = true;
        foreach ($groups as $key => $group) {
            foreach ($group as $fieldName => $condition) {
                $sql .= ($first ? '' : " $key ");

                if (!is_array($condition)) {
                    $sql .= $condition;
                } else {
                    $sql .= "({$this->generateLookupWhereGroup($condition)})";
                }

                $first = false;
            }
        }

        return $sql;
    }

    /**
     * @param object $object
     * @param array $data
     */
    public function offsetSet($object, $data = null): void
    {
        $index = count($this->conditions);
        parent::offsetSet($object, $index);
        $this->conditions[$index] = $data;
    }

    /**
     * @param object $object
     * @return array
     */
    public function &offsetGet($object): mixed
    {
        if (!$this->contains($object)) {
            $this->offsetSet($object, []);
        }

        return $this->conditions[parent::offsetGet($object)];
    }

    /**
     * @return array
     */
    public function getInfo(): mixed
    {
        return $this->conditions[parent::getInfo()];
    }

    /**
     * @param array $data
     */
    public function setInfo($data): void
    {
        $index = count($this->conditions);
        parent::setInfo($index);
        $this->conditions[$index] = $data;
    }
}
