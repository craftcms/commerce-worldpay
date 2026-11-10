<?php

declare(strict_types=1);

namespace CraftCms\CommerceWorldpay\Responses;

use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;
use Illuminate\Http\Client\Response;

/**
 * Wraps a Worldpay API response.
 *
 * Order requests (purchase and authorize) succeed when Worldpay responds with HTTP 200 and the expected
 * `paymentStatus`. Capture and refund requests succeed on HTTP 200 alone, since Worldpay responds to a refund
 * with an empty body.
 */
class PaymentResponse implements RequestResponseInterface
{
    /**
     * @param int $statusCode The HTTP status code
     * @param array<string, mixed>|null $data The decoded response body
     * @param string|null $successfulPaymentStatus The `paymentStatus` an order needs to be successful
     * (`SUCCESS` for purchases, `AUTHORIZED` for authorizations), or `null` for capture and refund requests.
     */
    public function __construct(
        protected readonly int $statusCode,
        protected readonly ?array $data,
        protected readonly ?string $successfulPaymentStatus = null,
    ) {
    }

    public static function fromHttpResponse(Response $response, ?string $successfulPaymentStatus = null): self
    {
        $data = $response->json();

        return new self($response->status(), is_array($data) ? $data : null, $successfulPaymentStatus);
    }

    public function isSuccessful(): bool
    {
        if ($this->statusCode !== 200) {
            return false;
        }

        return $this->successfulPaymentStatus === null || ($this->data['paymentStatus'] ?? null) === $this->successfulPaymentStatus;
    }

    public function isProcessing(): bool
    {
        return false;
    }

    public function isRedirect(): bool
    {
        return false;
    }

    public function getRedirectMethod(): string
    {
        return '';
    }

    public function getRedirectData(): array
    {
        return [];
    }

    public function getRedirectUrl(): string
    {
        return '';
    }

    public function getTransactionReference(): string
    {
        return (string)($this->data['orderCode'] ?? '');
    }

    public function getCode(): string
    {
        return (string)($this->data['customCode'] ?? '');
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getMessage(): string
    {
        if ($this->isSuccessful()) {
            return $this->successfulPaymentStatus ?? '';
        }

        // An API error, such as `{"httpStatusCode":400,"customCode":"BAD_REQUEST","message":"..."}`
        if (isset($this->data['message'])) {
            return (string)$this->data['message'];
        }

        // A declined payment, such as `{"paymentStatus":"FAILED","paymentStatusReason":"REFUSED"}`
        if ($this->successfulPaymentStatus !== null) {
            return (string)($this->data['paymentStatusReason'] ?? $this->data['paymentStatus'] ?? '');
        }

        return '';
    }
}
