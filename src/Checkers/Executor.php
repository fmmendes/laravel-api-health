<?php

namespace Pbmedia\ApiHealth\Checkers;

use Illuminate\Notifications\Notification;
use Pbmedia\ApiHealth\Checkers\Checker;
use Pbmedia\ApiHealth\Checkers\CheckerHasFailed;
use Pbmedia\ApiHealth\Checkers\CheckerSendsNotifications;
use Pbmedia\ApiHealth\Storage\CheckerState;

class Executor
{
    private $checker;
    private $state;
    private $exception;
    private $failed;
    private $notifiable;

    public function __construct(Checker $checker)
    {
        $this->checker    = $checker;
        $this->state      = new CheckerState($checker);
        $this->notifiable = app(config('api-health.notifications.notifiable'));
    }

    public function passes()
    {
        if (is_null($this->failed)) {
            $this->handle();
        }

        return !$this->failed;
    }

    public function failed()
    {
        if (is_null($this->failed)) {
            $this->handle();
        }

        return $this->failed;
    }

    public function getChecker()
    {
        return $this->checker;
    }

    public function getException()
    {
        return $this->exception;
    }

    public function handle()
    {
        $this->failed = false;

        try {
            $this->checker->run();
            $this->handlePassingChecker();
        } catch (CheckerHasFailed $exception) {
            $this->exception = $exception;
            $this->failed    = true;
            $this->handleFailedChecker();
        }

        return $this;
    }

    private function sendNotification(Notification $notification)
    {
        if (empty(config('api-health.notifications.via'))) {
            return;
        }

        return tap($notification, function ($notification) {
            $this->notifiable->notify($notification);
        });
    }

    private function handlePassingChecker()
    {
        if ($this->state->isFailed()) {
            $this->state->undoFailed();
        }
    }

    private function handleFailedChecker()
    {
        if (!$this->state->isFailed()) {
            $this->state->setToFailed();
        }

        if ($this->checker instanceof CheckerSendsNotifications) {
            $this->sendFailedNotification();
        }
    }

    private function sendFailedNotification()
    {
        if (!$this->state->shouldSentFailedNotification()) {
            return;
        }

        $notificationClass = $this->checker->getFailedNotificationClass();

        $notification = $this->sendNotification(
            new $notificationClass($this->checker, $this->exception)
        );

        $notification ? $this->state->markSentFailedNotification($notification) : null;
    }
}
