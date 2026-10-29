<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Model\Config\Source\Salesforce;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Salesforce authentication methods
 */
class AuthType implements OptionSourceInterface
{
    const CLIENT_CREDENTIALS = 'oauth_client_credentials';
    const PASSWORD = 'password';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::CLIENT_CREDENTIALS,
                'label' => __('OAuth 2.0 Client Credentials (recommended)'),
            ],
            [
                'value' => self::PASSWORD,
                'label' => __('Username, Password & Security Token (retired by Salesforce in Summer \'27)'),
            ],
        ];
    }
}
