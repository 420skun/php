<?php

// todo: add config if necessary

namespace Router;

require_once("Route.php");
require_once("Request.php");
require_once("RouterException.php");
require_once("../Shareable/Shareable.php");

// todo: expose some Root methods & make everything else private
class Router extends \Shareable
{
    public $Root;
    public $Request;
    private $Segments;

    const Methods = [ 'GET' => 1, 'POST' => 2, 'PUT' => 3, 'PATCH' => 4, 'DELETE' => 5];

    public function __construct(array $legal = NULL)
    {
        if (is_null($legal)) $legal = self::Methods;

        $this->Request = new \Request($legal);
        $this->Root = new Root($this->Request->Route);
        $this->Segments = Route::Segment($this->Request->Route);
    }

    // todo: allow for choosing to pass input
    public function _Act()
    {
        $route = $this->Request->Route;
        $method = $this->Request->Method;
        $arguments = array_slice($this->Segments, 1);
        $input = $this->Request->Input;

        // may need to abstract the condition checking / setting
        foreach ($this->Root->Examine($route, $method) as $handler)
        {
            if ($handler->NeedsInput)
            {
                if (empty($input)) \RouterException::INPUT();

                $handler->Handle($input, ...$arguments);
            }
            else $handler->Handle(...$arguments);
        }
    }

    public function _Root(int $method, callable $handler)
    {
        $this->Root->Handle($method, $handler);
    }

    public function _Missed(callable $handler)
    {
        $match = [];
        $this->Root->MatchRoute(Route::Segment($this->Request->Route), 1, $match);

        if (empty($match)) $handler();
    }

    /// help

    private function InitProxy(int $method, array $hierarchy, bool $needs_input)
    {
        $this->Root->InitMixedHierarchy($method, $hierarchy, [ 'NeedsInput' => $needs_input ]);
    }

    public function _Auto(int $method, array $levels, bool $needs_input = false)
    {
        static $mock = '[AutomaticRoute]';
        $hierarchy = [];
        $current =& $hierarchy;

        foreach ($levels as $level)
        {
            if (!is_null(current($levels))) $current[] = current($levels);
            next($levels);
            $current =& $current[$mock];
        }

        $this->InitProxy($method, $hierarchy, $needs_input);
    }

    public function _CheckInput(array $input_keys)
    {
        if (!empty(array_diff($input_keys, array_keys($this->Request->Input))))
        {
            \RouterException::INPUT();
        }
    }
}

class RESTRouter extends Router
{
    public function _GET(array $hierarchy, bool $n_i = false)
    {
        $this->InitProxy(self::Methods['GET'], $hierarchy, $n_i);
    }

    public function _POST(array $hierarchy, bool $n_i = true)
    {
        $this->InitProxy(self::Methods['POST'], $hierarchy, $n_i);
    }
    
    public function _PUT(array $hierarchy, bool $n_i = true)
    {
        $this->InitProxy(self::Methods['PUT'], $hierarchy, $n_i);
    }
    
    public function _PATCH(array $hierarchy, bool $n_i = true)
    {
        $this->InitProxy(self::Methods['PATCH'], $hierarchy, $n_i);
    }

    public function _DELETE(array $hierarchy, bool $n_i = true)
    {
        $this->InitProxy(self::Methods['DELETE'], $hierarchy, $n_i);
    }

    public function _Summary(array $get = [], array $post = [], array $put = [], array $patch = [], array $delete = [])
    {
        $this->_Auto(self::Methods['GET'], $get);
        $this->_Auto(self::Methods['POST'], $post, true);
        $this->_Auto(self::Methods['PUT'], $put, true);
        $this->_Auto(self::Methods['PATCH'], $patch, true);
        $this->_Auto(self::Methods['DELETE'], $delete);
        $this->_Act();
    }
}