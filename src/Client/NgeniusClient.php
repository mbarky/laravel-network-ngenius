<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Client;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use mbarky\Ngenius\Contracts\NgeniusClientContract;
use mbarky\Ngenius\DTO\CreateOrderData;
use mbarky\Ngenius\Enums\Environment;
use mbarky\Ngenius\Exceptions\AuthenticationException;
use mbarky\Ngenius\Exceptions\OrderCreationException;
use mbarky\Ngenius\Exceptions\PaymentVerificationException;

final class NgeniusClient implements NgeniusClientContract
{
    private const PAYMENT_CONTENT_TYPE = 'application/vnd.ni-payment.v2+json';

    public function __construct(
        private readonly TokenManager $tokenManager,
    ) {}

    /** @throws OrderCreationException|AuthenticationException */
    public function createOrder(CreateOrderData $data): NgeniusResponse
    {
        $env = Environment::fromConfig();
        $token = $this->tokenManager->getToken();
        $outlet = $env->outletReference();

        $payload = $this->buildOrderPayload($data);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => self::PAYMENT_CONTENT_TYPE,
                'Accept' => self::PAYMENT_CONTENT_TYPE,
            ])
                ->timeout(30)
                ->retry(2, 500, fn ($e) => ! ($e instanceof OrderCreationException))
                ->post(
                    "{$env->baseUrl()}/transactions/outlets/{$outlet}/orders",
                    $payload
                );
        } catch (RequestException $e) {
            if ($e->response->status() === 401) {
                $this->tokenManager->forgetToken();
                throw new AuthenticationException('N-Genius returned 401 during order creation.', 401, $e);
            }
            throw new OrderCreationException(
                "Order creation failed (HTTP {$e->response->status()}): ".$e->response->body(),
                $e->response->status(),
                $e
            );
        }

        if ($response->status() === 401) {
            $this->tokenManager->forgetToken();
            throw new AuthenticationException('N-Genius returned 401 during order creation.');
        }

        if (! $response->successful()) {
            throw new OrderCreationException(
                "Order creation failed (HTTP {$response->status()}): ".$response->body()
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new OrderCreationException('N-Genius order creation returned invalid JSON response.');
        }

        return NgeniusResponse::fromArray($json, $response->status());
    }

    /** @throws PaymentVerificationException|AuthenticationException */
    public function retrieveOrder(string $orderReference): NgeniusResponse
    {
        $env = Environment::fromConfig();
        $token = $this->tokenManager->getToken();
        $outlet = $env->outletReference();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => self::PAYMENT_CONTENT_TYPE,
            ])
                ->timeout(30)
                ->retry(2, 500)
                ->get("{$env->baseUrl()}/transactions/outlets/{$outlet}/orders/{$orderReference}");
        } catch (RequestException $e) {
            if ($e->response->status() === 401) {
                $this->tokenManager->forgetToken();
                throw new AuthenticationException('N-Genius returned 401 during order retrieval.', 401, $e);
            }
            throw new PaymentVerificationException(
                "Order retrieval failed (HTTP {$e->response->status()}): ".$e->response->body(),
                $e->response->status(),
                $e
            );
        }

        if ($response->status() === 401) {
            $this->tokenManager->forgetToken();
            throw new AuthenticationException('N-Genius returned 401 during order retrieval.');
        }

        if (! $response->successful()) {
            throw new PaymentVerificationException(
                "Order retrieval failed (HTTP {$response->status()}): ".$response->body()
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new PaymentVerificationException('N-Genius order retrieval returned invalid JSON response.');
        }

        return NgeniusResponse::fromArray($json, $response->status());
    }

    /** @return array<string, mixed> */
    private function buildOrderPayload(CreateOrderData $data): array
    {
        $payload = [
            'action' => $data->getAction()->value,
            'amount' => [
                'currencyCode' => $data->getAmount()->currency,
                'value' => $data->getAmount()->minorUnits,
            ],
            'emailAddress' => $data->getEmail(),
        ];

        if ($data->getMerchantOrderReference() !== null) {
            $payload['merchantOrderReference'] = $data->getMerchantOrderReference();
        }

        if ($data->getRedirectUrl() !== null) {
            $payload['redirectUrl'] = $data->getRedirectUrl();
        }

        if ($data->getLanguage() !== 'en') {
            $payload['language'] = $data->getLanguage();
        }

        if ($billing = $data->getBillingAddress()) {
            $billingData = array_filter([
                'firstName' => $billing->firstName,
                'lastName' => $billing->lastName,
                'address1' => $billing->address1,
                'city' => $billing->city,
                'countryCode' => $billing->countryCode,
            ]);

            if (! empty($billingData)) {
                $payload['billingAddress'] = $billingData;
            }
        }

        return $payload;
    }
}
