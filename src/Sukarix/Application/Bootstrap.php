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
        if (!$this->isStatelessRoute()) {
            $this->prepareSession();
        }
        $this->loadAppSetting();
        $this->detectCli();
        $this->loadRoutesAndAccess();
        $this->detectMultiLanguage();
    }

    // True for paths under SECURITY.stateless.prefixes (default /api) — no session is opened for these.
    protected function isStatelessRoute(): bool
    {
        $prefixes = $this->f3->get('SECURITY.stateless.prefixes') ?: ['/api'];
        if (\is_string($prefixes)) {
            $prefixes = array_map(static fn ($item) => mb_trim($item), explode(',', $prefixes));
        }
        $path     = (string) $this->f3->get('PATH');

        foreach ((array) $prefixes as $prefix) {
            if (str_starts_with($path, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function loadConfiguration(): void
    {
        // Use F3's built-in configuration loading
        $this->f3->config('config/classes.ini');
        $this->f3->config('config/default.ini');

        // Load additional configs from CONFIGS setting
        $this->f3->get('CONFIGS') && array_map(function($file) {
            $this->f3->config('config/' . mb_trim($file) . '.ini');
        }, $this->f3->get('CONFIGS'));

        $this->f3->config('config/config-' . $this->environment . '.ini');

        $this->applyEnvironmentOverrides();

        // custom error handler if debugging
        $this->debug = $this->f3->get('DEBUG');
    }

    /**
     * Let the environment have the last word on the settings a deployment owns.
     *
     * The configuration declares which variable stands in for which key:
     *
     *     [environment]
     *     APP_SMTP_HOST = mailer.smtp.host
     *
     * A variable that is unset or empty leaves the configured value alone, so a
     * deployment overrides only what it needs to and secrets stay out of the files
     * that are committed.
     */
    protected function applyEnvironmentOverrides(): void
    {
        $overrides = $this->f3->get('environment');

        if (!\is_array($overrides)) {
            return;
        }

        foreach ($overrides as $variable => $key) {
            if (!\is_string($key) || '' === $key) {
                continue;
            }

            $value = getenv((string) $variable);
            if (false !== $value && '' !== $value) {
                $this->f3->set($key, $value);
            }
        }
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
        if (null !== $this->session) {
            $this->f3->set('LANGUAGE', $this->session->get('locale'));
        }
    }

    protected function loadRoutesAndAccess(): void
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

    protected function detectMultiLanguage(): void
    {
        $this->f3->exists('MULTILANG.languages') && Injector::instance()->get('multi_language');
    }
}
