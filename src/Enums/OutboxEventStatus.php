<?php

namespace Outbox\Enums;

enum OutboxEventStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
}
