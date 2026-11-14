<?php

namespace App\Domain\Communication\Contracts;

use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use Carbon\CarbonInterface;

/**
 * Sends a guardian a message about their child. The driver is chosen by the
 * client's configuration: a log driver by default, or the WhatsApp Business
 * API. A driver throws when a message cannot be sent, so the queue retries it.
 */
interface GuardianNotifier
{
    /**
     * Tells the guardian that the student was marked absent on the day, for
     * the whole day or for a session or period named by $when.
     */
    public function absent(Guardian $guardian, Student $student, CarbonInterface $date, string $when): void;

    /**
     * Sends the guardian a one-time code to sign in. The code is valid for
     * five minutes. Drivers must never store the code outside the message.
     */
    public function otp(Guardian $guardian, string $code): void;

    /**
     * Whether this driver really sends messages. The log driver does not, so
     * in production a sign-in code is refused rather than promised.
     */
    public function canSend(): bool;
}
