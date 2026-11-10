<?php

declare(strict_types=1);

namespace CraftCms\CommerceWorldpay;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * A small client for the Worldpay Online Payments JSON API (v1).
 *
 * Worldpay authenticates requests with the service key, sent as-is in the `Authorization` header.
 *
 * Worldpay retired Worldpay Online Payments on 14 October 2022, and its API host no longer resolves.
 * See the README.
 */
class Client
{
    public const string BASE_URL = 'https://api.worldpay.com/v1';

    public function __construct(
        private readonly string $serviceKey,
    ) {
    }

    /**
     * Creates an order, which authorizes (and, unless `authorizeOnly` is set, captures) a payment with a token
     * from Worldpay.js.
     *
     * @param array<string, mixed> $data
     */
    public function createOrder(array $data): Response
    {
        return $this->request()->post('orders', $data);
    }

    /**
     * Captures an authorized order.
     *
     * @param int $amount The amount in the currency’s minor unit
     */
    public function captureOrder(string $orderCode, int $amount): Response
    {
        return $this->request()->post('orders/' . rawurlencode($orderCode) . '/capture', ['captureAmount' => $amount]);
    }

    /**
     * Refunds a captured order. Worldpay responds with an empty body.
     *
     * @param int $amount The amount in the currency’s minor unit
     */
    public function refundOrder(string $orderCode, int $amount): Response
    {
        return $this->request()->post('orders/' . rawurlencode($orderCode) . '/refund', ['refundAmount' => $amount]);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withHeaders(['Authorization' => $this->serviceKey])
            ->acceptJson()
            ->asJson();
    }
}
