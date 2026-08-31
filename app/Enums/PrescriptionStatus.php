<?php

namespace App\Enums;

enum PrescriptionStatus: string
{
    case Submitted = 'SUBMITTED';
    case AiProcessing = 'AI_PROCESSING';
    case OperatorReview = 'OPERATOR_REVIEW';
    case WaitingForPayment = 'WAITING_FOR_PAYMENT';
    case PaymentReview = 'PAYMENT_REVIEW';
    case Paid = 'PAID';
    case AiFailed = 'AI_FAILED';
    case Rejected = 'REJECTED';
}
