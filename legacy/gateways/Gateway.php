<?php

namespace craft\commerce\worldpay\gateways;

// Gateways saved with Worldpay for Craft Commerce 4 store this class name as their type.
/** @deprecated in 5.0.0. Use {@see \CraftCms\CommerceWorldpay\Gateway} instead. */
class_alias(\CraftCms\CommerceWorldpay\Gateway::class, 'craft\commerce\worldpay\gateways\Gateway');

/** @phpstan-ignore-next-line */
if (false) {
    class Gateway extends \CraftCms\CommerceWorldpay\Gateway
    {
    }
}
