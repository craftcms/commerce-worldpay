<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\worldpay\migrations;

use Craft;
use craft\commerce\worldpay\gateways\Gateway;
use craft\db\Migration;
use craft\db\Query;
use yii\db\Exception;

/**
 * Installation Migration
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  1.0
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Convert any built-in WorldPay gateways to ours
        $this->_convertGateways();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        return true;
    }

    /**
     * Converts any old school WorldPay gateways to this one
     *
     * @return void
     * @throws Exception
     */
    private function _convertGateways(): void
    {
        $gateways = (new Query())
            ->select(['id'])
            ->where(['type' => 'craft\\commerce\\gateways\\WorldPay_Json'])
            ->from(['{{%commerce_gateways}}'])
            ->all();

        $dbConnection = Craft::$app->getDb();

        foreach ($gateways as $gateway) {
            $values = [
                'type' => Gateway::class,
            ];

            $dbConnection->createCommand()
                ->update('{{%commerce_gateways}}', $values, ['id' => $gateway['id']])
                ->execute();
        }
    }
}
