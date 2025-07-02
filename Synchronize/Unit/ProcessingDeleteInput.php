<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Synchronize\Unit;

use Exception;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Phrase;
use TNW\Salesforce\Model\Queue;

/**
 * Processing Upsert Input
 */
class ProcessingDeleteInput extends ProcessingAbstract
{
    /**
     * @inheritdoc
     */
    public function description()
    {
        return __('Delete Input Phase');
    }

    /**
     * Analize
     *
     * @param AbstractModel $entity
     * @return bool|Phrase
     * @throws Exception
     */
    public function analize($entity)
    {
        /** @var Queue $queue */
        $queue = $this->load()->get('%s/queue', $entity);

        if ($this->isEntityEmpty($entity)) {
            throw new Exception(__('The entity related to the queue record #%1 is not available anymore', $queue->getId()).toString());
        }

        if ($queue->isProcessInputUpsert()) {
            return true;
        }

        return __('not delete input phase');
    }

    /**
     * @param AbstractModel $entity
     * @return bool
     */
    public function isEntityEmpty($entity)
    {
        /**
         * remove technical data
         */
        $data = $entity->getData();

        unset($data['config_website']);
        unset($data['product_options']);
        unset($data['_queue']);

        /**
         * check if no actual data is here
         */
        if (empty($data)) {
            return true;
        }

        return false;
    }
}
