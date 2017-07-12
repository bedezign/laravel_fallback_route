<?php
/**
 * @author    Steve Guns <steve@bedezign.com>
 * @package   com.bedezign.laravel
 * @copyright 2017 B&E DeZign
 */

namespace App;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

class FallbackRoute extends Route
{
    private $match;
    private $replace;
    private $matchedRoute;
    private $matching = false;

    /**
     * When specifying the parameters, it might be wise to include forward slashes where possible, eg "/v1/".
     * This helps you avoid replacing too much.
     *
     * @param string $match   The part of the URI to match
     * @param string $replace What to replace it with
     */
    public function __construct($match, $replace)
    {
        $this->match   = $match;
        $this->replace = $replace;

        $verbs = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'];
        parent::__construct($verbs, null, null);
        $this->name("fallback_{$match}_to_{$replace}");
    }

    public function matches(Request $request, $includingMethod = true)
    {
        if ($this->matching) {
            // Prevent reentry
            return false;
        }

        $request = $this->updateRequest($request);
        if (null === $request) {
            // Nothing to replace, we can't be of any help here
            return false;
        }

        $this->matching = true;
        // Ask the route collection for a match with the modified request (without upsetting the router and its current request)
        if ($this->matchedRoute = $this->router->getRoutes()->match($request)) {
            $this->impersonate($this->matchedRoute);
        }
        $this->matching = false;

        return null !== $this->route;
    }

    /**
     * Using the existing request, return a duplicate but change the REQUEST_URI according to our match and replace
     *
     * @param  Request $request
     * @return Request|null     Returns `null` if there is no REQUEST_URI
     */
    protected function updateRequest(Request $request)
    {
        $server = clone $request->server;
        $uri    = $server->get('REQUEST_URI');
        if (null === $uri) {
            return null;
        }

        $server->add(['REQUEST_URI' => str_replace($this->match, $this->replace, $uri)]);
        return $request->duplicate(null, null, null, null, null, $server->all());
    }

    protected function impersonate(Route $route)
    {
        foreach (array_except(get_object_vars($route), ['compiled']) as $key => $value) {
            $this->$key = $value;
        }
    }
}