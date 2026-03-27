<?php

namespace FedorenkoAlex322\LaravelOutbox\Enums;

enum OutboxEventStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
}
