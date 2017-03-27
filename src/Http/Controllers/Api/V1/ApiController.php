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
namespace Codex\Http\Controllers\Api\V1;

use Codex\Codex;
use Codex\Exception\CodexException;
use Codex\Http\Controllers\CodexController;
use Codex\Entities\Project;
use Codex\Entities\Ref;
use Codex\Support\Traits\HookableTrait;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Routing\Controller;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends Controller
{
    use HookableTrait;

    /** @var \Codex\Codex */
    protected $codex;

    /** @var \Codex\Addons\Addons */
    protected $addons;

    /** @var \Codex\Entities\Projects */
    protected $projects;

    /** @var \Codex\Menus\Menus */
    protected $menus;

    /** @var Project */
    protected $project;

    /** @var  Ref */
    protected $ref;

    /**
     * ApiController constructor.
     *
     * @param \Codex\Codex                       $codex
     * @param \Illuminate\Contracts\View\Factory $view
     */
    public function __construct(Codex $codex)
    {
        $this->codex    = $codex;
        $this->addons   = $this->codex->addons;
        $this->projects = $this->codex->projects;
        $this->menus    = $this->codex->menus;
    }

    protected function resolveRef($projectSlug, $ref = null)
    {
        if ( !$this->projects->has($projectSlug) ) {
            return $this->error('Project does not exist', Response::HTTP_BAD_REQUEST);
        }
        $this->project = $this->projects->get($projectSlug);

        $this->ref = $this->project->refs->get($ref);

        return $this->ref;
    }

    protected function response($data = [])
    {
        if ( $data instanceof Arrayable && !$data instanceof JsonSerializable ) {
            $data = $data->toArray();
        }

        return response()->json([ 'success' => true, 'message' => '', 'data' => $data ]);
    }

    /**
     * error method
     *
     * @param      string|CodexException $message
     * @param int                        $code
     * @param array                      $data
     *
     * @return mixed
     */
    protected function error($message, $code = 500, $data = [])
    {
        if ( $message instanceof CodexException ) {
            $ex      = $message;
            $message = $ex->getMessage();
            if ( config('app.debug') ) {
                $data = $ex->getTrace();
            }
        }
        return response()->json([ 'success' => false, 'message' => $message, 'data' => $data ], $code);
    }
}
