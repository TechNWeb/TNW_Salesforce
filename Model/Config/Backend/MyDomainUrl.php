<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Salesforce My Domain URL, saved as https://<host>
 */
class MyDomainUrl extends Value
{
    /**
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = trim((string)$this->getValue());
        if ($value === '') {
            return parent::beforeSave();
        }

        if (strpos($value, '://') === false) {
            $value = 'https://' . $value;
        }

        $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
        $host = strtolower((string)parse_url($value, PHP_URL_HOST));
        if ($scheme !== 'https' || $host === '') {
            throw new LocalizedException(
                __('Enter the Salesforce My Domain URL, for example https://acme.my.salesforce.com')
            );
        }

        if (in_array($host, ['login.salesforce.com', 'test.salesforce.com'], true)
            || substr($host, -strlen('.lightning.force.com')) === '.lightning.force.com'
        ) {
            throw new LocalizedException(__(
                '%1 cannot be used to connect. Enter your My Domain URL from Salesforce Setup > My Domain, '
                . 'for example https://acme.my.salesforce.com',
                $host
            ));
        }

        $this->setValue('https://' . $host);

        return parent::beforeSave();
    }
}
