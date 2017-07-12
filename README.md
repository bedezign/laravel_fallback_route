# Fallback Route

I wanted to know if it was technically possible to have a specific part of your routing fall back to another part.
The main reason for this was that if you are writing APIs and want to provide a new version, you don't have to rewrite all routes that haven't changed.

Please note: This is a proof of concept for that functionality. It has been tested (somewhat) and works (or seems to), but it might yield unexpected results. 
I am willing to provide limited support for it (answer questions etc), but at this time I have no plans of turning it into a fully working extension.

## Using it
Besides copying the `FallbackRoute.php` file somewhere in your application (and optionally modifying its namespace), you should add the following helper somewhere (perhaps in a service provider, or in your own helper files):

```
\Illuminate\Routing\Router::macro('fallbackUri', function($match, $replace) {
    $this->routes->add(
        (new \App\FallbackRoute($match, $replace))
                ->setRouter($this)
                ->setContainer($this->container)
    );
});
```

This adds a `fallbackUri` macro method to the router.

Imagine you have this routing setup:

```
Route::prefix('v1')->group(function() {
	Route::get('/orders', function(Request $request) {
		// ...
	}):
	
	Route::get('/items', function(Request $request) {
		// ...
	}):

	Route::get('/basket', function(Request $request) {
		// ...
	}):
});	

Route::prefix('v2')->group(function() {
	Route::get('/items', function(Request $request) {
		// ...
	}):

	Route::fallbackUri('/v2', '/v1');
});
```

Assuming the default laravel configuration you would be able to use `/api/v1/orders`, `/api/v1/items` and `/api/v1/basket`. 
Because of the `fallbackUri` call **at the end** of the `v2` prefix, you would call the upgraded `/items` in your second version, but it would fall back to the `/orders` and `/basket` from `v1`.  

## How it works

The `FallbackRoute`-class registers itself as an empty route for all possible verbs. 
When its `matches()`-function is called it will create a fake request where it changes the requested Uri accordig to what is specified. 

Taking the example above, imagine you are requesting `/api/v2/orders`: 

Because the fallback has been configured to replace `/v2` by `/v1` it will generate a new `Request` that has `/api/v1/orders` as the requested Uri. It will then query the `RouteCollection` for a match (outside of the `Router`. 

If a match is returned, the `FallbackRoute` will copy over all class properties so it can impersonate the matched route and it will behave just like the other route when executed.

## Of some importance

### Limitations
At this point you cannot call any of the regularly available methods on it (like `name`, `prefix` etc).
It is not technically possible to return a `RouteRegistrar` instance to provide this functionality as that will result in the creation of a regular `Route` class instead of our `FallbackRoute`. For that reason the helper directly adds a route, returning nothing.

### Request
I made sure not to override the current request in the router, so that everything seems as if the url used to call the new API revision works. 
If you are using HAL or HATEOAS you can still correctly generate the Urls to return to the client.

### Impersionation
Why copy over all matched route properties you might wonder? 

There is no way (that I know of) to replace the route being queried for a match with another route (it wouldn't make sense). 
The only solution is to behave like the actual matched route. 

You could alternatively not derivie from `Route` (so that we could use `__call` to proxy everything to the matched route), but this brings a whole new level of dificulties (like `artisan route:*` not working and so on). I've opted to do it like this as this is the lease invasive. 

### Position
As far as I know all routes are matched in order of adding them to the `Router`, be sure to add the fallback at the end of your API scheme, or it will be triggered earlier and produce unexpected results.

### artisan route:list

It will show up as a route for all verbs and it will give itself an applicable name so that you can recognize it:

```
+--------+--------------------------------+------+---------------------+---------+------------+
| Domain | Method                         | URI  | Name                | Action  | Middleware |
+--------+--------------------------------+------+---------------------+---------+------------+
|        | GET|HEAD|POST|PUT|PATCH|DELETE |      | fallback_/v2_to_/v1 | Closure |            |
+--------+--------------------------------+------+---------------------+---------+------------+
```
