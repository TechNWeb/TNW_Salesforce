<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Model\ResourceModel;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\ObjectManagerInterface;
use TNW\Salesforce\Synchronize\Queue\DependenciesQueue;

/**
 * Class Queue
 */
class Queue extends AbstractDb
{
    /**
     * @var string[][]
     */
    private $dependencies = [];

    /**
     * Serializable Fields
     *
     * @var array
     */
    protected $_serializableFields = [
        'entity_load_additional' => [[], []],
        'additional_data' => [[], []],
    ];

    /**
     * @var string[][]
     */
    private $dependenceByCode = [];

    /** @var string[][] */
    protected $appliedResolvers = [];

    /** @var ObjectManagerInterface  */
    protected $objectManager;

    /**
     * Construct
     */
    public function _construct()
    {
        $this->_init('tnw_salesforce_entity_queue', 'queue_id');
    }

    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        $connectionName = null
    ) {
        $this->objectManager = $objectManager;
        parent::__construct($context, $connectionName);
    }


    /**
     * After Save
     *
     * @param \TNW\Salesforce\Model\Queue $object
     * @return AbstractDb
     */
    protected function _afterSave(AbstractModel $object)
    {
        $this->saveDependence($object);
        return parent::_afterSave($object);
    }

    /**
     * Merge
     *
     * @param \TNW\Salesforce\Model\Queue $queue
     * @return array
     * @throws LocalizedException
     */
    public function convertToArray(\TNW\Salesforce\Model\Queue $queue): array
    {
        $this->serializeFields($queue);
        $data = $this->prepareDataForSave($queue);
        $this->unserializeFields($queue);

        return $data;
    }

    /**
     * @param AbstractModel $object
     *
     * @return array
     * @throws LocalizedException
     */
    public function prepareDataForSave(AbstractModel $object): array
    {
        return parent::_prepareDataForSave($object);
    }

    /**
     * Save Dependence
     *
     * @param \TNW\Salesforce\Model\Queue $object
     */
    public function saveDependence($object)
    {
        $data = [];
        foreach (array_map([$this, 'objectId'], $object->getDependence()) as $dependenceId) {
            if (empty($dependenceId)) {
                continue;
            }

            $data[] = [
                'queue_id' => $object->getId(),
                'parent_id' => $dependenceId
            ];
        }

        if (empty($data)) {
            return;
        }

        $this->getConnection()
            ->insertOnDuplicate($this->getTable('tnw_salesforce_entity_queue_relation'), $data);
    }

    /**
     * Object Id
     *
     * @param AbstractModel $object
     * @return mixed
     */
    private function objectId(AbstractModel $object)
    {
        return $object->getId();
    }

    /**
     * Get Dependence By Code
     *
     * @param string $code
     * @return string[]
     * @throws LocalizedException
     */
    public function getDependenceByCode($code)
    {
        if (empty($this->dependenceByCode)) {
            $data = $this->resolveDependencies();
            $this->dependenceByCode = $data;
        }

        if (!isset($this->dependenceByCode[$code])) {
            return [];
        }

        return $this->dependenceByCode[$code];
    }


    /**
     * @param array $arrObj
     * @return array
     */
    public function getDependObjCode(array $arrObj): array
    {
        $codes = [];
        array_walk($arrObj, function ($children) use (&$codes) {
            $codes[] = $children->code();
        });
        return $codes;
    }

    /**
     * @param string $element
     * @param array $elements
     * @param bool $isParent
     */
    public function addDependency(string $element, array $elements, $isParent = false): void
    {
        foreach ($elements as $el) {
            $parent = $element;
            if ($isParent) {
                $parent = $el;
                $el = $element;
            }
            $this->dependencies[] = [
                'childCode' => $el,
                'parentCode' => $parent
            ];
        }
    }

    /**
     * @param $elements
     */
    private function buildDependencies(iterable $elements): void
    {
        foreach ($elements as $resolveEntity) {
            $currentCode = $resolveEntity->code();
            if (!empty($this->appliedResolvers[$currentCode])) {
                return;
            } else {
                $this->appliedResolvers[$currentCode] = $currentCode;
            }

            if (!empty($resolveEntity->children()) && is_array($resolveEntity->children())) {
                $codes = $this->getDependObjCode($resolveEntity->children());
                $this->addDependency($currentCode, $codes);
                $this->buildDependencies($resolveEntity->children()); //recursion
            }
            if (!empty($resolveEntity->parents()) && is_array($resolveEntity->parents())) {
                $parentCodes = $this->getDependObjCode($resolveEntity->parents());
                $this->addDependency($currentCode, $parentCodes, true);
                $this->buildDependencies($resolveEntity->parents()); //recursion
            }
        }
    }

    /**
     * @return array
     */
    private function resolveDependencies(): array
    {
        $preQueue = $this->objectManager->create(DependenciesQueue::class);
        foreach ($preQueue->queueAddPool as $entotyCode => $entity) {
            if ($entity->resolves) {
                $this->buildDependencies($entity->resolves);
                $entity = null;
            }
        }
        $data = [];
        foreach ($this->dependencies as $row) {
            $data[$row['childCode']][] = $row['parentCode'];
            $data[$row['childCode']] = array_unique($data[$row['childCode']]);
        }

        $this->dependencies = [];
        return $data;
    }

    /**
     * Load By Child
     *
     * @param AbstractModel $object
     * @param string $code
     * @param int $childId
     * @return Queue
     * @throws LocalizedException
     */
    public function loadByChild(AbstractModel $object, $code, $childId)
    {
        return $this->load($object, $this->dependenceIdByCode($childId, $code), $this->getIdFieldName());
    }

    /**
     * Dependence Id By Code
     *
     * @param int $queueId
     * @param string $code
     * @return int
     * @throws LocalizedException
     */
    public function dependenceIdByCode($queueId, $code)
    {
        $select = $this->getConnection()->select()
            ->from(['relation' => $this->getTable('tnw_salesforce_entity_queue_relation')], [])
            ->joinInner(
                ['queue' => $this->getMainTable()],
                'relation.parent_id = queue.queue_id',
                ['queue_id']
            )
            ->where('relation.queue_id = :queue_id')
            ->where('queue.code = :code');

        return $this->getConnection()->fetchOne($select, ['queue_id' => $queueId, 'code' => $code]);
    }

    /**
     * Dependence Id By Entity Type
     *
     * @see \TNW\Salesforce\Service\Model\ResourceModel\Queue\GetDependenceIdsByEntityType
     *
     * @param int $queueId
     * @param string $entityType
     * @return int[]
     * @throws LocalizedException
     */
    public function dependenceIdsByEntityType($queueId, $entityType)
    {
        $select = $this->getConnection()->select()
            ->from(['relation' => $this->getTable('tnw_salesforce_entity_queue_relation')], [])
            ->joinInner(
                ['queue' => $this->getMainTable()],
                'relation.parent_id = queue.queue_id',
                ['queue_id']
            )
            ->where('relation.queue_id = :queue_id')
            ->where('queue.entity_type = :entity_type');

        return $this->getConnection()->fetchCol($select, ['queue_id' => $queueId, 'entity_type' => $entityType]);
    }

    /**
     * Load By Parent
     *
     * @param AbstractModel $object
     * @param string $code
     * @param int $parentId
     * @return Queue
     * @throws LocalizedException
     */
    public function loadByParent(AbstractModel $object, $code, $parentId)
    {
        return $this->load($object, $this->childIdByCode($parentId, $code), $this->getIdFieldName());
    }

    /**
     * Child Id By Code
     *
     * @param int $queueId
     * @param string $code
     * @return int
     * @throws LocalizedException
     */
    public function childIdByCode($queueId, $code)
    {
        $select = $this->getConnection()->select()
            ->from(['relation' => $this->getTable('tnw_salesforce_entity_queue_relation')], [])
            ->joinInner(
                ['queue' => $this->getMainTable()],
                'relation.queue_id = queue.queue_id',
                ['queue_id']
            )
            ->where('relation.parent_id = :queue_id')
            ->where('queue.code = :code');

        return $this->getConnection()->fetchOne($select, ['queue_id' => $queueId, 'code' => $code]);
    }

    /**
     * Child Ids By Entity Type
     *
     * @param int $queueId
     * @param string $entityType
     * @return int[]
     * @throws LocalizedException
     */
    public function childIdsByEntityType($queueId, $entityType)
    {
        $select = $this->getConnection()->select()
            ->from(['relation' => $this->getTable('tnw_salesforce_entity_queue_relation')], [])
            ->joinInner(
                ['queue' => $this->getMainTable()],
                'relation.queue_id = queue.queue_id',
                ['queue_id']
            )
            ->where('relation.parent_id = :queue_id')
            ->where('queue.entity_type = :entity_type');

        return $this->getConnection()->fetchCol($select, ['queue_id' => $queueId, 'entity_type' => $entityType]);
    }

    /**
     * @param array $entityIds
     * @return array
     */
    public function getDependentIds($entityIds)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from([
                'relation_table' => $this->getTable('tnw_salesforce_entity_queue_relation')
            ])
            ->joinLeft(
                ['queue_table' => $this->getTable('tnw_salesforce_entity_queue')],
                'queue_table.queue_id = relation_table.parent_id'
            )
            ->where($connection->prepareSqlCondition('queue_table .queue_id', ['in' => $entityIds]));

        return $connection->fetchCol($select);
    }
}
