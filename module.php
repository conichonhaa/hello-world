<?php

/**
 * Hello World Module for webtrees 2.2
 */

declare(strict_types=1);

namespace MyCustomModules\HelloWorld;

use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\HomePage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Services\TreeService;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Hello World Module
 */
return new class extends AbstractModule implements ModuleCustomInterface {
    use ModuleCustomTrait;

    /**
     * How should this module be identified in the control panel, etc.?
     */
    public function title(): string
    {
        return 'Hello World';
    }

    /**
     * A sentence describing what this module does.
     */
    public function description(): string
    {
        return 'Un module de démonstration Hello World pour webtrees 2.2';
    }

    /**
     * The person or organisation who created this module.
     */
    public function customModuleAuthorName(): string
    {
        return 'Votre Nom';
    }

    /**
     * The version of this module.
     */
    public function customModuleVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Where to get support for this module.
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/conichonhaa/hello-world';
    }

    /**
     * Additional/updated translations.
     * 
     * @param string $language
     * @return array<string>
     */
    public function customTranslations(string $language): array
    {
        $translations = [];
        
        switch ($language) {
            case 'fr':
                $translations = [
                    'Hello World' => 'Hello World',
                    'Hello World Demo' => 'Démonstration Hello World',
                ];
                break;
            case 'en':
            default:
                $translations = [
                    'Hello World' => 'Hello World',
                    'Hello World Demo' => 'Hello World Demo',
                ];
                break;
        }

        return $translations;
    }

    /**
     * Bootstrap the module
     */
    public function boot(): void
    {
        // Enregistrement des routes ou autres initialisations si nécessaire
    }

    /**
     * The module's schema version.
     */
    public function schemaVersion(): string
    {
        return '1';
    }
};
