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

/**
 * todo: namespace on sup resource
 * todo: inherit formats on sub resource
 */
class Resource
{

    /**
     * The root url prepend
     * @var string
     */
    public $namespace;
    /**
     * The parent resource singular name
     * @var string
     */
    public $resource;

    /**
     * The parent resource plural name
     * @var string
     */
    public $resources;

    /**
     * The parent controller
     *
     * @var string
     */
    public $controller;



    /**
     * values are: fuzzy
     * @var mixed|null
     */
    public $mode;

    /*
     * build some basic regexes
     */
    private $idNum = '(\d+)';               // and qty of digits
    private $idAny = '([^\.\/]*)';          // any until dot or slash is reached
    private $formatAny = '\.??([^\.\/]*)';  // dot then any
    private $formatNum = '\.??(\d+)';       // dot then digits
    private $formatNone = ' ';              // turn off formats
    private $formatDefaults = '\.??(xml|json|html|csv|pdf)'; // dot the sample extensions


    public function __construct($mode = null, $namespace = '')
    {

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
        $names['resource'] = $resource = str_singular(strtolower($resource));   # \Cake\Utility\Inflector::singularize( strtolower($resource) );
        $names['resources']            = str_plural(strtolower($resource)); #\Cake\Utility\Inflector::pluralize(   strtolower($resource) );
        $names['controller']           = ucfirst($resource).'Controller';

        return $names;
    }


    /**
     * Process input options and set defaults
     *
     * @param      $resource
     * @param      $options
     *
     * @return array
     */
    static function options($resource, $options)
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
        #$options['mode'] = isset($options['mode']) ? $options['mode'] : null;

        if(!isset($options['mode']))
            $options['mode'] = Config::get('app.resourcefulMode', 'fuzzy');

        /*
         * Set additional routes (GET only)
         */
        if(isset($options['action']))
        {
            if(is_string($options['action']))
            {
                $options['action'] = explode("|", $options['action']);
            }
        }
        else
            $options['action'] = null;

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
            $options['formats'] = false;
        }

        if(isset($options['handles']))
        {
            $options['namespace'] = rtrim( $options['handles'] , '/').'/';
        }
        elseif(isset($options['namespace']))
        {
            $options['namespace'] = rtrim( $options['namespace'] , '/').'/';
        }
        else
        {
            $options['namespace'] = "";
        }


        if($options['embed'])
        {
            $options['resourceActions'] = [
                'index'    => '@'.    strtolower($options['resources']).'Index',
                'indexall' => '@'. 'all'.ucfirst($options['resources']).'Index',
                'show'     => '@show'.   ucfirst($options['resource']),
                'new'      => '@new'.    ucfirst($options['resource']),
                'edit'     => '@edit'.   ucfirst($options['resource']),
                'create'   => '@create'. ucfirst($options['resource']),
                'store'    => '@store'.  ucfirst($options['resource']),
                'update'   => '@update'. ucfirst($options['resource']),
                'destroy'  => '@destroy'.ucfirst($options['resource']),
            ];

        }
        else
        {
            $options['resourceActions'] =  [
                'index'    => '@index',
                'indexall' => '@indexAll',
                'show'     => '@show',
                'new'      => '@new',
                'edit'     => '@edit',
                'create'   => '@create',
                'store'    => '@store',
                'update'   => '@update',
                'destroy'  => '@destroy',
            ];
        }



        return $options;

    }


    /**
     * The default entry point
     * e.g. Resource::route(...);
     *
     * acceptable options:  uses|resources|action|mode|before|after|handles
     *
     * @param            $resource
     * @param null|array $options
     *
     * @return $this
     */
    static function route($resource, $options = [])
    {
        $options['parent'] = true;
        $options = static::options($resource, $options);

        $router = new Resource();

        $router->resource   = $options['resource'];
        $router->resources  = $options['resources'];
        $router->controller = $options['controller'];
        $router->namespace  = $options['namespace'];
        $router->formats    = $options['formats'];

        if(!isset($options['mode']))
            $router->mode = Config::get('app.resourcefulMode', 'fuzzy');
        else
            $router->mode = $options['mode'];

        if($options['has_filters'])
            return $router->group($options['filters'], $options);

        return $router->chainRoutes($options);
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
            $pre = $this->namespace;
        else
            $pre = $this->namespace.$this->resources.'/{pid}/';
        if($options['formats'])
            $ext = $options['formats'];
        else
            $ext = $this->formats;

        $__         = $options['resourceActions'];
        $resource   = $options['resource'];
        $resources  = $options['resources'];
        $controller = $options['controller'];


        if($options['action'])
        {
            foreach($options['action'] as $action)
            {
                Route::get($pre.$resources.'/{id}/'.$action,   ['as' => $action."_".$resource,  'uses' => $controller.'@'.$action]);
            }
        }

        if($ext)
        {
            Route::get($pre.$resources."/create.{format}",    $controller.$__['create'])->where('format', $ext);
            Route::get($pre.$resources."/{id}/edit.{format}", $controller.$__['edit'])->where('format', $ext);
            Route::get($pre.$resources."/{id}.{format}",      $controller.$__['show'])->where('format', $ext);
            Route::get($pre.$resources.".{format}",           $controller.$__['index'])->where('format', $ext);
        }

        Route::get   ($pre.$resources.'/create',        ['as' => "create_".$resource,'uses' => $controller.$__['create']]);
        Route::get   ($pre.$resources.'/{id}/edit',     ['as' =>   "edit_".$resource,'uses' => $controller.$__['edit']  ]);
        Route::get   ($pre.$resources.'/{id}',          ['as' =>           $resource,'uses' => $controller.$__['show']  ]);
        Route::get   ($pre.$resources.'/{id}/delete',   $controller.$__['destroy']);
        Route::delete($pre.$resources.'/{id}',          $controller.$__['destroy']);
        Route::post  ($pre.$resources,                  $controller.$__['store']  );
        Route::put   ($pre.$resources.'/{id}',          $controller.$__['update'] );
        Route::patch ($pre.$resources.'/{id}',          $controller.$__['update'] );
        if($parent)
        {
            Route::get   ($pre.$resources,                  ['as' => $resources,        'uses' => $controller.$__['index']   ]);
        }
        else
        {
            Route::get   ($options['namespace'].$resources, ['as' => 'all_'.$resources, 'uses' => $controller.$__['indexall']]);
            Route::get   ($pre.$resources,                  ['as' => $resources,        'uses' => $controller.$__['index']   ]);
        }


        if($this->mode == 'fuzzy')
        {
            // attempt to match anything reasonably close
            Route::get($pre.$resources.'/{id}/destroy', $controller.$__['destroy']);
            Route::get($pre.$resources.'/new',          $controller.$__['create']);
        }
    }



    /**
     * Make a child resource
     *
     * @param       $resource
     * @param array $options
     *
     * @return $this
     */
    public function with($resource, $options = [])
    {
        $options['parent'] = false;
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





}
