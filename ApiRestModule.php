<?php

declare(strict_types=1);

use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\FlashMessages;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ApiRestModule extends AbstractModule implements ModuleCustomInterface, ModuleConfigInterface, RequestHandlerInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;

    private const SETTING_API_KEY = 'API_KEY';
    private const SETTING_ENABLED = 'API_ENABLED';
    private const SETTING_LOG_REQUESTS = 'LOG_REQUESTS';

    /**
     * Module constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Module name - must match directory name
     */
    public function name(): string
    {
        return 'api-rest';
    }

    /**
     * Module title
     */
    public function title(): string
    {
        return 'API REST JSON Sécurisée';
    }

    /**
     * Module description
     */
    public function description(): string
    {
        return 'API REST JSON sécurisée avec authentification par clé API pour accéder aux données généalogiques';
    }

    /**
     * Module version
     */
    public function customModuleVersion(): string
    {
        return '1.1.0';
    }

    /**
     * Module author name
     */
    public function customModuleAuthorName(): string
    {
        return 'Votre Nom';
    }

    /**
     * Module support URL
     */
    public function customModuleSupportUrl(): string
    {
        return '';
    }

    /**
     * Bootstrap the module
     */
    public function boot(): void
    {
        // Enregistrer les routes de l'API uniquement si activée
        if ($this->getPreference(self::SETTING_ENABLED, '0') === '1') {
            Registry::routeFactory()->routeMap()
                ->get('/api/individuals/{tree}', $this)
                ->tokens(['tree' => '\d+']);
                
            Registry::routeFactory()->routeMap()
                ->get('/api/families/{tree}', $this)
                ->tokens(['tree' => '\d+']);
        }
    }

    /**
     * Configuration page
     */
    public function getConfigLink(): string
    {
        return route('module', [
            'module' => $this->name(),
            'action' => 'Config'
        ]);
    }

    /**
     * Handle configuration requests
     */
    public function getConfigAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $api_key = $this->getPreference(self::SETTING_API_KEY, '');
        $api_enabled = $this->getPreference(self::SETTING_ENABLED, '0');
        $log_requests = $this->getPreference(self::SETTING_LOG_REQUESTS, '0');

        return $this->viewResponse('modules/api-rest/config', [
            'title' => $this->title(),
            'module' => $this->name(),
            'api_key' => $api_key,
            'api_enabled' => $api_enabled,
            'log_requests' => $log_requests,
        ]);
    }

    /**
     * Save configuration
     */
    public function postConfigAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        if (isset($params['generate_key'])) {
            // Générer une nouvelle clé API
            $new_key = $this->generateApiKey();
            $this->setPreference(self::SETTING_API_KEY, $new_key);
            FlashMessages::addMessage('Nouvelle clé API générée avec succès');
        } else {
            // Sauvegarder les paramètres
            $this->setPreference(self::SETTING_ENABLED, $params['api_enabled'] ?? '0');
            $this->setPreference(self::SETTING_LOG_REQUESTS, $params['log_requests'] ?? '0');
            FlashMessages::addMessage('Configuration sauvegardée');
        }

        return redirect($this->getConfigLink());
    }

    /**
     * Handle HTTP requests
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Vérifier si l'API est activée
        if ($this->getPreference(self::SETTING_ENABLED, '0') !== '1') {
            return $this->createJsonResponse(['error' => 'API désactivée'], 503);
        }

        // Vérifier l'authentification
        if (!$this->isAuthenticated($request)) {
            return $this->createJsonResponse(['error' => 'Clé API invalide ou manquante'], 401);
        }

        $route = $request->getAttribute('route');
        $tree_id = $route->getArgument('tree');
        $path = $request->getUri()->getPath();

        // Logger la requête si activé
        if ($this->getPreference(self::SETTING_LOG_REQUESTS, '0') === '1') {
            $this->logRequest($request);
        }

        try {
            $tree = Registry::treeService()->find((int) $tree_id);
            
            if (!$tree instanceof Tree) {
                return $this->createJsonResponse(['error' => 'Arbre généalogique introuvable'], 404);
            }

            if (strpos($path, '/api/individuals/') === 0) {
                return $this->getIndividuals($request, $tree);
            } elseif (strpos($path, '/api/families/') === 0) {
                return $this->getFamilies($request, $tree);
            }

            return $this->createJsonResponse(['error' => 'Endpoint non trouvé'], 404);

        } catch (\Exception $e) {
            return $this->createJsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Vérifier l'authentification par clé API
     */
    private function isAuthenticated(ServerRequestInterface $request): bool
    {
        $stored_key = $this->getPreference(self::SETTING_API_KEY, '');
        
        if (empty($stored_key)) {
            return false;
        }

        // Vérifier dans les headers
        $auth_header = $request->getHeaderLine('Authorization');
        if (preg_match('/^Bearer\s+(.+)$/i', $auth_header, $matches)) {
            return hash_equals($stored_key, $matches[1]);
        }

        // Vérifier dans les paramètres GET
        $params = $request->getQueryParams();
        if (isset($params['api_key'])) {
            return hash_equals($stored_key, $params['api_key']);
        }

        return false;
    }

    /**
     * Générer une clé API sécurisée
     */
    private function generateApiKey(): string
    {
        return bin2hex(random_bytes(32)); // 64 caractères hexadécimaux
    }

    /**
     * Logger les requêtes API
     */
    private function logRequest(ServerRequestInterface $request): void
    {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'user_agent' => $request->getHeaderLine('User-Agent')
        ];

        // Écrire dans un fichier de log (vous pouvez adapter selon vos besoins)
        $log_file = WT_DATA_DIR . 'logs/api-rest.log';
        error_log(json_encode($log_entry) . PHP_EOL, 3, $log_file);
    }

    /**
     * Get individuals from tree
     */
    private function getIndividuals(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $params = $request->getQueryParams();
        $limit = min((int) ($params['limit'] ?? 100), 1000);
        $offset = max((int) ($params['offset'] ?? 0), 0);
        
        $individuals = [];
        $count = 0;
        
        foreach ($tree->individuals() as $individual) {
            if ($count < $offset) {
                $count++;
                continue;
            }
            
            if (count($individuals) >= $limit) {
                break;
            }
            
            $birth_date = $individual->getBirthDate();
            $death_date = $individual->getDeathDate();
            
            $individuals[] = [
                'id' => $individual->xref(),
                'name' => $individual->fullName(),
                'birth_date' => $birth_date->isOK() ? $birth_date->minimumDate()->format('Y-m-d') : null,
                'death_date' => $death_date->isOK() ? $death_date->minimumDate()->format('Y-m-d') : null,
                'birth_place' => $individual->getBirthPlace()->gedcomName(),
                'death_place' => $individual->getDeathPlace()->gedcomName(),
                'sex' => $individual->sex(),
                'url' => $individual->url()
            ];
            
            $count++;
        }

        return $this->createJsonResponse([
            'data' => $individuals,
            'meta' => [
                'total' => $tree->individuals()->count(),
                'limit' => $limit,
                'offset' => $offset,
                'returned' => count($individuals)
            ]
        ]);
    }

    /**
     * Get families from tree
     */
    private function getFamilies(ServerRequestInterface $request, Tree $tree): ResponseInterface
    {
        $params = $request->getQueryParams();
        $limit = min((int) ($params['limit'] ?? 100), 1000);
        $offset = max((int) ($params['offset'] ?? 0), 0);
        
        $families = [];
        $count = 0;
        
        foreach ($tree->families() as $family) {
            if ($count < $offset) {
                $count++;
                continue;
            }
            
            if (count($families) >= $limit) {
                break;
            }
            
            $marriage_date = $family->getMarriageDate();
            $husband = $family->husband();
            $wife = $family->wife();
            
            $families[] = [
                'id' => $family->xref(),
                'husband' => $husband ? [
                    'id' => $husband->xref(),
                    'name' => $husband->fullName()
                ] : null,
                'wife' => $wife ? [
                    'id' => $wife->xref(),
                    'name' => $wife->fullName()
                ] : null,
                'marriage_date' => $marriage_date->isOK() ? $marriage_date->minimumDate()->format('Y-m-d') : null,
                'marriage_place' => $family->getMarriagePlace()->gedcomName(),
                'children_count' => $family->children()->count(),
                'url' => $family->url()
            ];
            
            $count++;
        }

        return $this->createJsonResponse([
            'data' => $families,
            'meta' => [
                'total' => $tree->families()->count(),
                'limit' => $limit,
                'offset' => $offset,
                'returned' => count($families)
            ]
        ]);
    }

    /**
     * Create JSON response
     */
    private function createJsonResponse(array $data, int $status = 200): ResponseInterface
    {
        $response = Registry::responseFactory()->createResponse($status);
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}
