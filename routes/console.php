<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('maintenance:generate-tasks')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('rentals:calculate-late-fees')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('rentals:send-overdue-reminders')->dailyAt('08:00')->withoutOverlapping();
