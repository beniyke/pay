<?php

declare(strict_types=1);

namespace Pay\Schedules;

use Cron\Interfaces\Schedulable;
use Cron\Schedule;

class PayVerifyPendingPaymentSchedule implements Schedulable
{
    /**
     * Define the schedule for the task.
     *
     * @param Schedule $schedule
     *
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        $schedule->task()
            ->signature('pay:verify-pending')
            ->hourly();
    }
}
