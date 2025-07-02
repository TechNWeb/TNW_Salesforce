<?php
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace TNW\Salesforce\Model\ResourceModel;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Manager;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use RuntimeException;
use Throwable;

/**
 * Class Prequeue
 */
class Salesforceids extends AbstractDb
{
    public const OBJECT_IDS_KEY = 'object_ids';
    private const CHUNK_SIZE = 500;

    /**
     * @var Manager|null
     */
    private $eventManager;

    /**
     * @param Context $context
     * @param string $connectionName
     * @param Manager|null $eventManager
     */
    public function __construct(
        Context $context,
        $connectionName = null,
        ?Manager $eventManager = null
    ) {
        parent::__construct($context, $connectionName);
        $this->eventManager = $eventManager ?? ObjectManager::getInstance()->get(Manager::class);
    }

    /**
     * Construct
     */
    public function _construct()
    {
        $this->_init('tnw_salesforce_objects', 'id');
    }

    /**
     * Delete by object_id field
     *
     * @param array $objectIds
     *
     * @return void
     */
    public function deleteByObjectIds(array $objectIds): void
    {
        if (!$objectIds) {
            return;
        }

        $errorMessages = [];
        foreach (array_chunk($objectIds, self::CHUNK_SIZE) as $chunkedFieldValues) {
            $connection = $this->getConnection();
            try {
                $this->eventManager->dispatch('tnw_salesforce_objects_delete_before', [self::OBJECT_IDS_KEY => $objectIds]);
                $connection->beginTransaction();
                $connection->delete(
                    $this->getMainTable(),
                    $connection->quoteInto(
                        'object_id IN (?)',
                        $objectIds
                    )
                );
                $connection->commit();
                $this->eventManager->dispatch('tnw_salesforce_objects_delete_after', [self::OBJECT_IDS_KEY => $objectIds]);
            } catch (Throwable $e) {
                $connection->rollBack();
                $errorMessages[] = $e->getMessage();
            }
        }

        if ($errorMessages) {
            throw new RuntimeException(implode(' ', $errorMessages));
        }
    }
}
