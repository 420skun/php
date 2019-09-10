<?php

namespace Router;

class RouteHandler
{
    public $Method;
    public $Handler;
    public $Specific;
    public $Meta = [];

    function __construct(int $method, callable $handler, bool $specific = false)
    {
        $this->Method = $method;
        $this->Handler = $handler;
        $this->Specific = $specific;
    }

    function Handle(...$arguments)
    {
        ($this->Handler)(...$arguments);
    }

    function __set(string $key, $value)
    {
        $this->Meta[$key] = $value;
    }

    function __get(string $key)
    {
        if (!array_key_exists($key, $this->Meta)) return false;
        else return $this->Meta[$key];
    }
}

class Route
{
    // State

    public $Text;
    public $Outgoing = [];
    public $Handlers = [];

    const MISSING = 'missing';
    const ROOT = 'root';

    public function __construct(string $text)
    {
        $this->Text = urlencode($text);
    }

    // Utility

    static function Segment(string $route)
    {
        return array_filter(explode('/', $route));
    }

    public function Serial()
    {
        return json_encode($this, JSON_PRETTY_PRINT);
    }

    public function Exists()
    {
        return $this->Text != self::MISSING;
    }

    // Get & Add

    public function Get(string $text, bool $create = false): Route
    {
        $result = new MissingRoute();

        foreach ($this->Outgoing as $og)
        {
            if ($og->Text == $text) $result = $og;
        }

        if (!$result->Exists() && $create) $result = $this->Add($text);

        return $result;
    }

    public function Add(string $text): Route
    {
        if (!strlen($text)) return new MissingRoute(); 

        $n = $this->Get($text);

        if (!$n->Exists())
        {
            $n = new Route($text);
            $this->Outgoing[] = $n;
        }

        return $n;
    }

    public function GetRoute(string $route, bool $create = false): array
    {
        $current = $this;
        $result = [];

        foreach (self::Segment($route) as $segment)
        {
            $current = $current->Get($segment, $create);

            if (!$current->Exists())
            {
                $result = [new MissingRoute()];
                break;
            }

            $result[] = $current;
        }

        return $result;
    }

    public function AddRoute(string $route)
    {
        return $this->GetRoute($route, true);
    }

    // Handle & Match

    public function Handle(int $method, callable $handler, bool $specific = true, array $meta = [])
    {
        $h = $this->Handlers[] = new RouteHandler($method, $handler, $specific);

        foreach ($meta as $key => $value) $h->$key = $value;

        return $this;
    }

    public function Match(int $method = NULL, bool $specific = false)
    {
        $result = [];

        foreach ($this->Handlers as $handler)
        {
            if ((!is_null($method) && $handler->Method == $method) && $handler->Specific == $specific)
            {
                $result[] = $handler;
            }
        }

        return $result;
    }

    function MatchRoute(array $route, int $method, array &$results)
    {
        if (empty($route)) return;

        $segment = array_splice($route, 0, 1)[0];

        if (empty($route))
        {
            if ($this->Text == $segment && !empty($specific = $this->Match($method, true)))
            {
                $results = array_merge($results, $specific);
            }
            else $results = array_merge($results, $this->Match($method));
        } 
        else foreach ($this->Outgoing as $og) $og->MatchRoute($route, $method, $results);
    }

    // Determine Specificity

    public static function IsSpecific(string &$route)
    {
        $segments = self::Segment($route);
        $end = count($segments) - 1;
        $is_specific = true;

        foreach ($segments as $i => &$segment)
        {
            if (substr($segment, 0, 1) == '[' && substr($segment, -1, 1) == ']')
            {
                $segment = substr($segment, 1, -1);
                if ($i == $end) $is_specific = false;
            }
        }

        $route = implode('/', $segments);

        return $is_specific;
    }

    // Init

    public function InitRoute(string $route, int $method = NULL, callable $handler = NULL, array $meta = [])
    {
        $specific = self::IsSpecific($route);
        $result = $this->AddRoute($route);
        $end = end($result);

        if (!is_null($method) && !is_null($handler)) $end->Handle($method, $handler, $specific, $meta);

        return $end;
    }

    public function InitHierarchy(int $method, array $segments, array $meta)
    {
        foreach ($segments as $segment => $handler_or_segments)
        {
            if (is_array($handler_or_segments))
            {
                $this->InitRoute($segment)->InitHierarchy($method, $handler_or_segments);
            }
            else $this->InitRoute($segment, $method, $handler_or_segments, $meta);
        }
    }

    public function InitMixedHierarchy(int $method, array $segments, array $meta = [], bool $specific = false)
    {
        foreach ($segments as $segment => $handler_or_segments)
        {
            if (is_int($segment))
            {
                if (is_callable($handler_or_segments))
                {
                    $this->Handle($method, $handler_or_segments, $specific, $meta);
                }
            }
            else if (is_array($handler_or_segments))
            {
                $this->InitRoute($segment)->InitMixedHierarchy($method, $handler_or_segments, $meta, self::IsSpecific($segment));
            }
        }
    }

    // Action

    function Act(string $route, int $method, ...$arguments)
    {
        $handlers = [];
        $this->MatchRoute(self::Segment($route), $method, $handlers);

        foreach ($handlers as $handler) $handler->Handle(...$arguments);
    }

    function Examine(string $route, int $method)
    {
        $handlers = [];
        $this->MatchRoute(self::Segment($route), $method, $handlers);
        return $handlers;
    }
}

class MissingRoute extends Route
{
    public function __construct() { parent::__construct(self::MISSING); }
}

class Root extends Route
{
    public function __construct(string &$route) 
    { 
        parent::__construct(self::ROOT); 
        
        $route = $this->Text . '/' . $route;
    }
}