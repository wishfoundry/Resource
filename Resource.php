<?php
/*
|--------------------------------------------------------------------------
| Resourceful router
|--------------------------------------------------------------------------
|
| Setup sensible default routes per resource
|
| This tool allows for single level nested resources (e.g. /posts/45/comment/12)
| but no deeper. You can however implement multiple nested resources using:
|
| Resource::route('post')->with('comments')->with('images')->with('category');
|
*/
class Resource
{

    /**
     * The parent resource singular name
     * @var string
     */
    protected $resource;

    /**
     * The parent resource plural name
     * @var string
     */
    protected $resources;

    /**
     * The parent controller
     *
     * @var string
     */
    protected $controller;

    /**
     * add extra routes with / appended
     * @var bool
     */
    protected $preserveSlashes;

    /**
     * when preserving slashes, which redirect to use
     *
     * @var int|null
     */
    protected $redirectCode;

    /**
     * values are: strict|moderate|permissive|fuzzy
     * @var mixed|null
     */
    public $mode;

    public function __construct($mode = null)
    {
        if(!$mode)
            $this->mode = Config::get('app.resourcefulMode', 'fuzzy');
        else
            $this->mode = $mode;

        switch($this->mode)
        {
            case 'strict':
                $this->preserveSlashes = false; // <-- rails default
                $this->redirectCode    = null;
                break;

            case 'moderate':
                $this->preserveSlashes = true;
                $this->redirectCode    = 301;
                break;

            case 'permissive':
                $this->preserveSlashes = true;
                $this->redirectCode    = 302;
                break;

            // allow every reasonable variant
            case 'fuzzy':
                $this->preserveSlashes = true;
                $this->redirectCode    = 302;
                break;
        }
    }

    /**
     * generate plural and singular resource names
     * @param $resource
     *
     * @return array
     */
    static function makeResourceNames($resource)
    {
        $names = [];
        $names['resource'] = $resource = \Cake\Utility\Inflector::singularize( strtolower($resource) );
        $names['resources']            = \Cake\Utility\Inflector::pluralize(   strtolower($resource) );
        $names['controller']           = ucfirst($resource).'Controller';

        return $names;

    }

    /**
     * Wrap routes in a group when filters are required
     *
     * @param array  $filters
     * @param array  $options
     *
     * @return $this
     */
    protected  function group($filters, $options)
    {
        $me = $this;
        Route::group($filters, function() use($me, $options)
        {
            $me->makeRoute($options);
        });

        return $this;
    }

    /**
     * Process input options and set defaults
     *
     * @param      $resource
     * @param      $options
     * @param bool $isParent
     *
     * @return array
     */
    static function options($resource, $options, $isParent = false)
    {
        /*
         * Set default controller name and plural resource name
         * Strategy:
         * --resources should always be plural
         * --controllers should be singular: ResourceController
         */
        if(is_null($options))
            $options = array();

        if(isset($options['uses']))
        {
            $options['resource'] = $resource;
            $options['resources'] = $options['resources'] ?: $resource;
        }
        else
        {
            $names = static::makeResourceNames($resource);

            $options['resource']   = $names['resource'];
            $options['resources']  = $names['resources'];
            $options['controller'] = $names['controller'];
        }
        $options['mode'] = isset($options['mode']) ? $options['mode'] : null;

        /*
         * Set additional routes (GET only)
         */
        if(isset($options['adds']))
        {
            if(is_string($options['adds']))
            {
                $options['adds'] = explode("|", $options['adds']);
            }
        }
        else
            $options['adds'] = null;

        if(isset($options['before']) or isset($options['after']))
        {
            $options['has_filters'] = true;
            if(isset($options['before']))
                $options['filters']['before'] = $options['before'];

            if(isset($options['after']))
                $options['filters']['after']  = $options['after'];
        }
        else
            $options['has_filters'] = false;

        /*
         * Embed the child resource.
         * if true:  use 'ResourceController' with child's method names
         * if false: use 'ChildController' ans standard resourceful method names
         */
        if(!isset($options['embed']))
            $options['embed'] = false;

        /*
         * Formats are dot separates extensions on routes (e.g. resource.xml, resource.json)
         *
         * expects a string separated by pipes (e.g. "json|html|xml" )
         */
        if(!isset($options['formats']))
        {
            $options['formats'] = null;
        }


        if($options['embed'])
        {
            $options['resourceActions'] = [
                'index'   => '@'.strtolower($options['resources']).'Index',
                'show'    => '@show'.ucfirst($options['resource']),
                'new'     => '@new'.ucfirst($options['resource']),
                'edit'    => '@edit'.ucfirst($options['resource']),
                'create'  => '@create'.ucfirst($options['resource']),
                'store'   => '@store'.ucfirst($options['resource']),
                'update'  => '@update'.ucfirst($options['resource']),
                'destroy' => '@destroy'.ucfirst($options['resource']),
            ];

        }
        else
        {
            $options['resourceActions'] =  [
                'index'   => '@index',
                'show'    => '@show',
                'new'     => '@new',
                'edit'    => '@edit',
                'create'  => '@create',
                'store'   => '@store',
                'update'  => '@update',
                'destroy' => '@destroy',
            ];
        }
        if($isParent)
            $options['parent'] = true;

        return $options;

    }


    /**
     * The default entry point
     * e.g. Resource::route(...);
     *
     * acceptable options:  uses|resources|adds|mode|before|after
     *
     * @param            $resource
     * @param null|array $options
     *
     * @return $this
     */
    static function route($resource, $options = null)
    {
        $options = static::options($resource, $options, true);

        $router = new Resource($options['mode']);

        $router->resource   = $options['resource'];
        $router->resources  = $options['resources'];
        $router->controller = $options['controller'];

        if($options['has_filters'])
            return $router->group($options['filters'], $options, 'makeRoute');

        return $router->makeRoute($options);
    }

    protected function chainRoutes($options)
    {
        $this->makeRoute($options);
        return $this;
    }

    /**
     * Make the main resource routes
     *
     * @param $options
     *
     * @return $this
     */
    protected function makeRoute($options)
    {
        $parent = $options['parent'];
        if($parent)
            $pre = "";
        else
            $pre = $this->resources.'/{pid}/';

        $__ = $options['resourceActions'];

        $resource   = $options['resource'];
        $resources  = $options['resources'];
        $controller = $options['controller'];

        $code = $this->redirectCode;

        if($options['adds'])
        {
            foreach($options['adds'] as $action)
            {
                Route::get($resources.'/{id}/'.$action,   ['as' => $action."_".$resource,  'uses' => $controller.'@'.$action]);
            }
        }

        if(!is_null($options['formats']))
        {
            $ext = $options['formats'];
            Route::get($pre.$resources."/create.{format}",    $controller.$__['new'])->where('format', $ext);
            Route::get($pre.$resources."/{id}/edit.{format}", $controller.$__['edit'])->where('format', $ext);
            Route::get($pre.$resources."/{id}.{format}",      $controller.$__['show'])->where('format', $ext);
            Route::get($pre.$resources.".{format}",           $controller.$__['index'])->where('format', $ext);
        }

        Route::get   ($pre.$resources.'/create',        ['as' => "create_".$resource,'uses' => $controller.$__['create']]);
        Route::get   ($pre.$resources.'/{id}/edit',     ['as' => "edit_".$resource,  'uses' => $controller.$__['edit']  ]);
        Route::get   ($pre.$resources.'/{id}',          ['as' => $resource,          'uses' => $controller.$__['show']  ]);
        Route::get   ($pre.$resources.'/{id}/delete',   $controller.$__['destroy']);
        Route::delete($pre.$resources.'/{id}',          $controller.$__['destroy']);
        Route::post  ($pre.$resources,                  $controller.$__['store']  );
        Route::put   ($pre.$resources.'/{id}',          $controller.$__['update'] );
        Route::patch ($pre.$resources.'/{id}',          $controller.$__['update'] );
        if($parent)
        {
        Route::get   ($resources,                       ['as' => $resources,         'uses' => $controller.$__['index'] ]);
        }
        else
        {
        Route::get   ($resources,                       ['as' => 'all_'.$resources,  'uses' => $controller.$__['index'] ]);
        Route::get   ($pre.$resources,                  ['as' => $resources,         'uses' => $controller.$__['index'] ]);
        }



        if($this->preserveSlashes)
        {
            if($code)
            {
                if($parent)
                {
                    Route::get($resources.'/{id}/edit/',
                        function($id) use($code, $resources) {
                            Redirect::to("{$resources}/{$id}/edit", $code);
                    });
                    Route::get($resources.'/{id}/create/',
                        function($id) use($code, $resources) {
                            Redirect::to("{$resources}/{$id}/create", $code);
                    });
                    Route::get($resources.'/{id}/',
                        function($id) use($code, $resources) {
                            Redirect::to("{$resources}/{$id}", $code);
                    });
                    Route::get($resources.'/',
                        function()    use($code, $resources) {
                            Redirect::to($resources, $code);
                    });

                }
                else
                {
                    $pr = $this->resources;
                    Route::get($pre.$resources.'/{id}/edit/',
                        function($pid, $id) use($code, $resources, $pr) {
                            Redirect::to("{$pr}/{$pid}/{$resources}/{$id}/edit", $code);
                    });
                    Route::get($pre.$resources.'/{id}/create/',
                        function($pid, $id) use($code, $resources, $pr) {
                            Redirect::to("{$pr}/{$pid}/{$resources}/{$id}/create", $code);
                    });
                    Route::get($pre.$resources.'/{id}/',
                        function($pid, $id) use($code, $resources, $pr) {
                            Redirect::to("{$pr}/{$pid}/{$resources}/{$id}", $code);
                    });
                    Route::get($pre.$resources.'/',
                        function($pid)       use($code, $resources, $pr) {
                            Redirect::to("{$pr}/{$pid}/{$resources}", $code);
                    });
                }
            }


            if($this->mode == 'fuzzy')
            {
                // attempt to match anything reasonably close
                Route::get   ($pre.$resources.'/{id}/destroy/', $controller.$__['destroy']);
                Route::get   ($pre.$resources.'/{id}/destroy',  $controller.$__['destroy']);
                Route::get   ($pre.$resources.'/{id}/delete/',  $controller.$__['destroy']);
                Route::get   ($pre.$resources.'/new',             $controller.$__['create']);
                Route::get   ($pre.$resources.'/new/',            $controller.$__['create']);
                Route::put   ($pre.$resources.'/{id}/',         $controller.$__['update']);
                Route::patch ($pre.$resources.'/{id}/',         $controller.$__['update']);
                Route::delete($pre.$resources.'/{id}/',         $controller.$__['destroy']);
            }
        }

    }

    /**
     * Make a child resource
     *
     * @param      $resource
     * @param null $options
     *
     * @return $this
     */
    public function with($resource, $options = null)
    {

        $options = $this->options($resource, $options);

        if($options['has_filters'])
        {
            return $this->group($options['filters'], $options);
        }
        else
        {
            return $this->chainRoutes($options);
        }

    }





}
