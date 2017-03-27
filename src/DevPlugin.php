<?php
/**
 * Part of the Codex Project packages.
 *
 * License and copyright information bundled with this package in the LICENSE file.
 *
 * @author Robin Radic
 * @copyright Copyright 2017 (c) Codex Project
 * @license http://codex-project.ninja/license The MIT License
 */

namespace Codex\Dev;

use Barryvdh\Debugbar\DataCollector\LogsCollector;
use Codex\Addons\Addons;
use Codex\Addons\Annotations as CA;
use Codex\Addons\BasePlugin;
use Codex\Codex;
use Codex\Dev\Debugbar\CodexSimpleCollector;
use Codex\Entities\Document;
use Codex\Http\Controllers\CodexDocumentController;
use Codex\Support\Traits\CodexProviderTrait;
use DebugBar\Bridge\MonologCollector;
use Illuminate\Foundation\Application;
use Laradic\ServiceProvider\ServiceProvider;

/**
 * This is the class DevPlugin.
 *
 * @package Codex\Dev
 * @author  Robin Radic
 * @CA\Plugin("dev")
 */
class DevPlugin extends BasePlugin
{

    protected $middleware = [
        Middleware\CodexDev::class,
    ];

    protected $shared = [
        'codex.dev.debugbar.collector' => Debugbar\CodexSimpleCollector::class,
    ];

    /** @var Dev */
    protected $dev;


    protected function isEnabled()
    {
        return $this->config->get('codex.dev', false) === true;
    }

    public function boot()
    {
        if ($this->isEnabled()) {
            $app = parent::boot();
            $this->bootMetas();
            $this->bootDebugbar();
        }
        return $this->app;
    }

    public function register()
    {
        $this->addons = Addons::getInstance();
        $this->registerDev();
        Codex::extend('dev', 'codex.dev');
        if ($this->isEnabled()) {
            $app = parent::register();
            $this->registerMetas();
            $this->registerDebugbar();
            $this->registerMeasurements();


        }
        return $this->app;
    }

    protected function registerMetas()
    {
        if (class_exists('Laradic\Idea\IdeaServiceProvider') && $this->app->bound('idea-meta') === false) {
            $this->app->register('Laradic\Idea\IdeaServiceProvider');
        }
    }

    protected function registerDebugbar()
    {
        if (class_exists('Barryvdh\Debugbar\ServiceProvider') && $this->app->bound('debugbar') === false) {
            $this->app->register('Barryvdh\Debugbar\ServiceProvider');
        }
    }

    protected function registerDev()
    {
        $this->app->instance('codex.dev', $this->dev = Dev::getInstance());

        $this->hook('document:render', function () {
            $this->dev->stopBenchmark(true);
        });

    }

    protected function bootMetas()
    {
        if ($this->app->bound('laradic.idea.meta')) {
            $this->app[ 'laradic.idea.meta' ]->add('codex', Metas\CodexMeta::class);
            $this->app[ 'laradic.idea.meta' ]->add('codex-projects', Metas\CodexProjectsMeta::class);
        }
    }

    protected function bootDebugbar()
    {

        if ($this->app->bound('debugbar')) {
            $db = $this->app->make('debugbar');
            $db->addCollector($codexCollector = $this->app->make('codex.dev.debugbar.collector'));
            $db->addCollector($logsCollector = new Debugbar\CodexLogsCollector(config('codex.paths.log', ''), 'codexLogs'));

            $this->hook('controller:document', function (CodexDocumentController $controller, Document $document) use ($db) {
                //, Codex $codex, Project $project
                /** @var CodexSimpleCollector $collector */
                $collector = $db->getCollector('codex');
                $collector->setDocument($document);
                $collector->data()->set('document', $document->toArray());
                $collector->data()->set('hookPoints', \Codex\Codex::$hookPoints);
                $collector->data()->set('views', $this->addons()->views->toArray());
                $hooks = [];
                foreach ($this->addons()->hooks->all() as $hook) {
                    $hooks[] = [ 'name' => $hook[ 'name' ], 'class' => $hook[ 'class' ], 'listener' => $hook[ 'listener' ] ];
                }
                $collector->data()->set('hooks', $hooks);
            });
        }
    }

    protected function bootMenus()
    {
        $this->view('menus.dev', 'codex::menus.header-dropdown');

        $menu = $this->codex()->menus->add('dev', [ 'title' => 'Dev' ]);

        $menu->add('dev-log', 'Log')->setAttribute('href', '#'); //route('codex.dev.log'));
        $this->codex()->theme->pushContentToStack('nav', $this->view('layout'), function ($view) use ($menu) {
            return $menu->render();
        });
    }

    /**
     * registerMeasurements method
     *
     * @param $app
     */
    protected function registerMeasurements()
    {
        $this->app->booting(function () {
            $this->dev->startMeasure('Application::boot', 'Application::boot loops trough all registered Service Providers and boots them');
        });
        $this->app->booted(function () {
            $this->dev->stopMeasure('Application::boot');
        });

        // Projects
        $this->hook('projects:construct', function () {
            $this->dev->startMeasure('Projects::findAndRegisterAll');
        });
        $this->hook('projects:constructed', function () {
            $this->dev->stopMeasure('Projects::findAndRegisterAll');
        });

        // Refs
        $this->hook('refs:construct', function () {
            $this->dev->startMeasure('Refs::resolveRefs');
        });
        $this->hook('refs:constructed', function () {
            $this->dev->stopMeasure('Refs::resolveRefs');
        });

        // Documents
        $this->hook('documents:construct', function () {
            $this->dev->startMeasure('Documents::resolveAll');
        });
        $this->hook('documents:constructed', function () {
            $this->dev->stopMeasure('Documents::resolveAll');
        });

        // Render
        $this->hook('document:render', function (Document $document) {
            $this->dev->startMeasure("Document::render({$document->getPath()})");
        });
        $this->hook('document:rendered', function (Document $document) {
            $this->dev->stopMeasure("Document::render({$document->getPath()})");
        });

        // Controller
        $this->hook('controller:document:get', function () {
            $this->dev->startMeasure('CodexDocumentController::getDocument');
        });
        $this->hook('controller:document', function () {
            $this->dev->stopMeasure('CodexDocumentController::getDocument');
        });
    }
}
