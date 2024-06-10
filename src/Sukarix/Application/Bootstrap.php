<?php

declare(strict_types=1);

namespace Sukarix\Application;

use Sukarix\Core\Injector;
use Sukarix\Enum\ErrorChannel;
use Sukarix\Mail\MailSender;
use Sukarix\Notification\Notifier;
use Sukarix\Utils\Time;
use Tracy\Debugger;

/**
 * fat-free framework application initialisation.
 */
class Bootstrap extends Boot
{
    public function __construct()
    {
        if (\PHP_SAPI !== 'cli') {
            $this->logFileName = 'app';
        } else {
            $this->logFileName = 'cli';
        }
        $this->logSession = true;

        parent::__construct();

        $this->handleException();
        $this->createDatabaseConnection();
        $this->prepareSession();
        $this->loadAppSetting();
        $this->detectCli();
        $this->loadRoutesAndAssets();
    }

    protected function loadConfiguration(): void
    {
        $this->f3->config('config/classes.ini');
        $this->f3->config('config/default.ini');

        $this->f3->get('CONFIGS') && array_map(function($file) {
            $this->f3->config('config/' . trim($file) . '.ini');
        }, $this->f3->get('CONFIGS'));

        $this->f3->config('config/config-' . $this->environment . '.ini');

        // custom error handler if debugging
        $this->debug = $this->f3->get('DEBUG');
    }

    protected function handleException(): void
    {
        // Tracy consumes about 300 Ko of memory
        Debugger::enable(3 !== $this->debug ? Debugger::Production : Debugger::Development, $this->f3->get('ROOT') . '/' . $this->f3->get('LOGS'));
        if (Debugger::$productionMode) {
            Debugger::$onFatalError = [function($exception): void {
                $errorChannel = $this->f3->get('error.channel');
                if (ErrorChannel::EMAIL === $errorChannel) {
                    /**
                     * @var MailSender $mailer
                     */
                    $mailer = Injector::instance()->get('mailer');
                    $mailer->sendExceptionEmail($exception);
                } elseif (ErrorChannel::ZULIP === $errorChannel) {
                    /**
                     * @var Notifier $notifier
                     */
                    $notifier = Injector::instance()->get('notifier');
                    $notifier->notifyException($exception);
                }
            }];
        }

        // default error pages if site is not being debugged
        if (!$this->isCli && empty($this->debug)) {
            $this->f3->set(
                'ONERROR',
                function(): void {
                    header('Expires:  ' . Time::http(time() + \Base::instance()->get('error.ttl')));
                    if ('404' === \Base::instance()->get('ERROR.code')) {
                        include_once 'templates/error/404.phtml';
                    } else {
                        include_once 'templates/error/error.phtml';
                    }
                }
            );
        }
    }

    protected function loadAppSetting(): void
    {
        $this->f3->set('LANGUAGE', $this->session->get('locale'));
    }

    protected function loadRoutesAndAssets(): void
    {
        // setup routes
        // @see http://fatfreeframework.com/routing-engine
        // First, we load routes from ini file then load custom environment routes

        $this->f3->config('config/routes.ini');
        $this->f3->config('config/routes-' . $this->environment . '.ini');

        if (!$this->isCli) {
            // load routes access policy
            $this->f3->config('config/access.ini');
        } else {
            // load routes access policy for CLI
            $this->f3->config('config/access-cli.ini');
        }
    }
}
