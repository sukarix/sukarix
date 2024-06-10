<?php

declare(strict_types=1);

namespace Sukarix\Actions;

use SimpleXMLElement;
use Sukarix\Behaviours\HasAccess;
use Sukarix\Behaviours\HasAssets;
use Sukarix\Behaviours\HasEvents;
use Sukarix\Behaviours\HasF3;
use Sukarix\Behaviours\HasI18n;
use Sukarix\Behaviours\HasSession;
use Sukarix\Behaviours\LogWriter;
use Sukarix\Configuration\Environment;
use Sukarix\Core\Injector;
use Sukarix\Core\Tailored;
use Sukarix\Enum\UserRole;
use Sukarix\Enum\UserStatus;
use Sukarix\Helpers\Assets;
use Sukarix\Models\User;

/**
 * Base Controller Class.
 */
abstract class Action extends Tailored
{
    use HasAccess;
    use HasAssets;
    use HasEvents;
    use HasF3;
    use HasI18n;
    use HasSession;
    use LogWriter;

    public const JSON            = 'Content-Type: application/json; charset=utf-8';
    public const APPLICATION_XML = 'Content-Type: application/xml; charset=UTF-8';
    public const CSV             = 'Content-Type: text/csv; charset=UTF-8';
    public const TEXT            = 'Content-Type: text/plain; charset=utf-8';
    public const XML             = 'Content-Type: text/xml; charset=UTF-8';

    /**
     * The view name to render.
     *
     * @var string
     */
    protected $view;

    /**
     * @var string
     */
    private $headerAuthorization;

    /**
     * @var string
     */
    private $templatesDir;

    /**
     * initialize controller.
     */
    public function __construct()
    {
        $this->f3 = \Base::instance();

        $this->parseHeaderAuthorization();

        $this->templatesDir = $this->f3->get('ROOT') . $this->f3->get('BASE') . '/../app/ui/';
    }

    public function beforeroute(): void
    {
        $this->access->authorize(
            $this->getRole(),
            function($route, $subject): void {
                $this->onAccessAuthorizeDeny($route, $subject);
            }
        );
        if ($this->session->isLoggedIn() && $this->f3->get('ALIAS') === $this->f3->get('ALIASES.login')) {
            $this->f3->reroute($this->f3->get('ALIASES.dashboard'));
        } elseif ('POST' === $this->f3->VERB && !$this->session->validateToken()) {
            $this->f3->reroute($this->f3->get('PATH'));
        }
        // Rerouted paged uri having the page value less than one
        if ($this->f3->exists('PARAMS.page') && $this->f3->get('PARAMS.page') < 1) {
            $uri = $this->f3->get('PATH');
            $uri = preg_replace('/\/' . $this->f3->get('PARAMS.page') . '$/', '/1', $uri);
            $this->f3->reroute($uri);
        }
    }

    public function onAccessAuthorizeDeny($route, $subject): void
    {
        $this->logger->warning('Access denied to route ' . $route . ' for subject ' . ($subject ?: 'unknown'));
        $this->f3->error(404);
    }

    /**
     * @param null   $view
     * @param null   $partial
     * @param string $mime
     */
    public function render($view = null, $partial = null, $mime = 'text/html'): void
    {
        // automatically load the partial from the class namespace
        if (null === $partial) {
            $partial = str_replace(['\\_'], '/', str_replace('actions\\_', '', $this->f3->snakecase(static::class)));
        }
        $this->f3->set('partial', $this->setPartial($partial));
        if (null === $view) {
            $view = $this->view ?: $this->f3->get('view.default');
        }
        // This required to register the template extensions before rendering it
        // We do it at this time because we are sure that we want to render starting from here
        Injector::instance()->get('html');
        // add controller assets to assets.css and assets.js hive properties
        echo \Template::instance()->render($view . '.phtml', $mime);
    }

    /**
     * @param array|string $json
     * @param int          $statusCode
     */
    public function renderJson($json, $statusCode = 200): void
    {
        header('HTTP/1.1 ' . $statusCode);
        header(self::JSON);
        echo \is_string($json) ? $json : json_encode($json);
    }

    /**
     * @param array|string $text
     * @param int          $statusCode
     */
    public function renderText($text, $statusCode = 200): void
    {
        header('HTTP/1.1 ' . $statusCode);
        header(self::TEXT);
        echo \is_string($text) ? $text : implode("\n", $text);
    }

    public function renderCsv($object): void
    {
        header(self::CSV);
        header('Content-Disposition: attachement; filename="' . $this->f3->hash($this->f3->get('TIME') . '.csv"'));
        echo $object;
    }

    public function renderXML(?string $view = null, ?string $cacheKey = null, int $ttl = 0): void
    {
        if (!empty($view)) {
            $this->view = $view;
        }
        // Set the XML header
        header('Content-Type: text/xml; charset=UTF-8');

        // Use caching only in production
        if (!empty($cacheKey) && Environment::isProduction()) {
            if (!$this->f3->exists($cacheKey)) {
                $this->f3->set($cacheKey, $this->parseXMLView(), $ttl);
            }
            echo $this->f3->get($cacheKey);
        } else {
            echo $this->parseXMLView();
        }
    }

    /**
     * @param $xml SimpleXMLElement
     */
    public function renderRawXml($xml): void
    {
        // Set the XML header
        header('Content-Type: text/xml; charset=UTF-8');
        echo $xml->asXML();
    }

    public function renderXmlString($xml = null): void
    {
        header('Content-Type: text/xml; charset=UTF-8');

        echo $xml;
    }

    /**
     * @return mixed
     */
    public function getDecodedBody(): array
    {
        return json_decode($this->f3->get('BODY'), true);
    }

    protected function setPartial($name)
    {
        return "{$name}.phtml";
    }

    protected function parseHeaderAuthorization(): void
    {
        if ($header = $this->f3->get('HEADERS.Authorization')) {
            $this->headerAuthorization = str_replace('Basic ', '', $header);
        }
    }

    protected function isApiUserVerified(): bool
    {
        if ($credentials = $this->getCredentials()) {
            $user = new User();
            $user = $user->getByEmail($credentials[0]);

            return
                $user->valid()
                && UserStatus::ACTIVE === $user->status
                && UserRole::API === $user->role
                && $user->verifyPassword($credentials[1]);
        }

        return false;
    }

    protected function getRole(): string
    {
        if ($this->session->getRole()) {
            return $this->session->getRole();
        }
        if ($this->isApiUserVerified()) {
            return UserRole::API;
        }

        return '';
    }

    protected function getCredentials(): array
    {
        if (!$this->headerAuthorization) {
            return [];
        }

        $credentials = base64_decode($this->headerAuthorization, true);
        $credentials = explode(':', $credentials ?: '');

        if (2 !== \count($credentials)) {
            return [];
        }

        return $credentials;
    }

    private function parseXMLView(?string $view = null): string
    {
        $xmlResponse = new \SimpleXMLElement(\Template::instance()->render($this->view . '.xml'));

        $xmlDocument                     = new \DOMDocument('1.0');
        $xmlDocument->preserveWhiteSpace = false;
        $xmlDocument->formatOutput       = true;

        $xmlDocument->loadXML($xmlResponse->asXML());

        return $xmlDocument->saveXML();
    }
}
