<?php

declare(strict_types=1);

namespace Sukarix\Application;

use DB\SQL;
use Sukarix\Behaviours\LogWriter;
use Sukarix\Configuration\Environment;
use Sukarix\Core\Session;

abstract class Boot
{
    use LogWriter;

    /**
     * @var \F3
     */
    protected $f3;

    protected string $environment;

    /**
     * @var mixed
     */
    protected $debug;

    protected Session $session;

    protected bool $logSession = false;

    protected string $logFileName;

    protected bool $isCli;

    public function __construct()
    {
        $this->f3    = \Base::instance();
        $this->isCli = $this->f3->get('CLI');

        $this->setPhpVariables();
        $this->detectEnvironment();

        // start configuration F3 framework from this point
        $this->loadConfiguration();
        $this->setupLogging();
    }

    public function prepareSession(): void
    {
        // store the session into sqlite database file
        $className     = $this->f3->get('classes.session');
        $this->session = new $className(\Registry::get('db'), $this->f3->get('session.table'), false);
        \Registry::set('session', $this->session);
    }

    public function start(): void
    {
        // start the framework
        $this->f3->run();
        $this->logPerformanceMetrics();
    }

    protected function detectEnvironment(): void
    {
        /*
         * read config and overrides.
         *
         * @see http://fatfreeframework.com/framework-variables#configuration-files
         * set the environment dynamically depending on the server IP address
         */

        if (true !== $this->f3->exists('GET.statera')) {
            if (str_ends_with(Environment::getHostName(), '.test')) {
                $this->f3->set(Environment::CONFIG_KEY, Environment::DEVELOPMENT);
            } else {
                $this->f3->set(Environment::CONFIG_KEY, Environment::PRODUCTION);
            }
        } else {
            $this->f3->set(Environment::CONFIG_KEY, Environment::TEST);
            \Cache::instance()->reset();
        }

        $this->environment = $this->f3->get(Environment::CONFIG_KEY);
    }

    abstract protected function loadConfiguration();

    protected function setupLogging(): void
    {
        // setup daily rotation logging for access and errors
        // @todo gzip compress previous day log and delete the .log file
        $this->f3->set('application.logfile', $this->f3->get('LOGS') . $this->logFileName . '-' . date('Y-m-d') . '.log');
        ini_set('error_log', $this->f3->get('LOGS') . $this->logFileName . '-error-' . date('Y-m-d') . '.log');

        $this->initLogWriter();
    }

    abstract protected function handleException();

    protected function createDatabaseConnection(): void
    {
        /**
         * setup database connection params.
         *
         * @see http://fatfreeframework.com/databases
         */
        $db = new SQL(
            $this->f3->get('db.dsn'),
            $this->f3->get('db.username'),
            $this->f3->get('db.password')
        );

        if (true === $this->logSession) {
            $db->log(true === $this->f3->get('log.session'));
        }
        \Registry::set('db', $db);
    }

    protected function detectCli(): void
    {
        // If in CLI mode run that from here on...
        if ($this->isCli) {
            $this->f3->config('config/routes-cli.ini');
            $this->f3->set('ROOT', $this->f3->get('ROOT') . '/../public');
        }
    }

    abstract protected function loadRoutesAndAccess();

    protected function logPerformanceMetrics(): void
    {
        // Log session SQL queries only in the development environment for debugging purposes
        if ($this->f3->get('log.session') && $dbLog = \Registry::get('db')->log()) {
            $this->logger->debug($dbLog);
        }

        $this->logger->notice(sprintf(
            '[%s] Script executed in %s seconds using %s/%s MB memory/peak | Data Received: %s | Data Sent: %s',
            $this->f3->get('PATH'),
            round(microtime(true) - $this->f3->get('TIME'), 3),
            round(memory_get_usage() / 1024 / 1024, 3),
            round(memory_get_peak_usage() / 1024 / 1024, 3),
            mb_strlen(serialize($this->f3->get('SERVER'))),
            $this->f3->get('SERVER.SERVER_PROTOCOL') ?? 'HTTP/1.1'
        ));
    }

    protected function setPhpVariables(): void
    {
        // add php variables configuration here
        setlocale(LC_TIME, 'en_GB.utf8', 'eng');
    }
}
