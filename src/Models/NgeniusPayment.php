<?php

declare(strict_types=1);

namespace mbarky\Ngenius\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $payable_type
 * @property int|string|null $payable_id
 * @property string|null $merchant_order_reference
 * @property string $ngenius_order_reference
 * @property string $outlet_reference
 * @property string $action
 * @property string $currency
 * @property int $amount_minor
 * @property string $status
 * @property string $payment_url
 * @property array<mixed, mixed>|null $raw_order_response
 * @property array<mixed, mixed>|null $raw_status_response
 * @property string|null $last_webhook_event
 * @property string|null $last_webhook_event_id
 * @property array<mixed, mixed>|null $last_webhook_payload
 * @property Carbon|null $paid_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $refunded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class NgeniusPayment extends Model
{
    protected $table = 'ngenius_payments';

    protected $guarded = [];

    protected $casts = [
        'raw_order_response' => 'array',
        'raw_status_response' => 'array',
        'last_webhook_payload' => 'array',
        'amount_minor' => 'integer',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
