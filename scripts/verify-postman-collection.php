<?php

$routesJson = shell_exec('php artisan route:list --path=api/v1 --json');
$routes = json_decode($routesJson, true);

$laravelPaths = [];
foreach ($routes as $route) {
    $uri = $route['uri'] ?? '';
    if (! str_starts_with($uri, 'api/v1/')) {
        continue;
    }
    $path = substr($uri, strlen('api/v1/'));
    $methods = explode('|', $route['method'] ?? 'GET');
    $methods = array_values(array_filter($methods, fn ($m) => $m !== 'HEAD'));
    foreach ($methods as $method) {
        $laravelPaths[strtoupper($method).' '.$path] = true;
    }
}

$postman = json_decode(file_get_contents(dirname(__DIR__, 2).'/Olabisiolai Local.postman_collection.json'), true);

$postmanPaths = [];
$walk = function (array $items) use (&$walk, &$postmanPaths) {
    foreach ($items as $item) {
        if (isset($item['request'])) {
            $method = $item['request']['method'] ?? 'GET';
            $path = implode('/', $item['request']['url']['path'] ?? []);
            $path = preg_replace('/\{\{[^}]+\}\}/', '{id}', $path);
            $postmanPaths[strtoupper($method).' '.$path] = $item['name'] ?? '';
        }
        if (isset($item['item'])) {
            $walk($item['item']);
        }
    }
};
$walk($postman['item'] ?? []);

$missing = [];
foreach (array_keys($laravelPaths) as $key) {
    $normalized = preg_replace('/\{[^}]+\}/', '{id}', $key);
    $found = false;
    foreach (array_keys($postmanPaths) as $pk) {
        $pn = preg_replace('/\{[^}]+\}/', '{id}', $pk);
        if ($pn === $normalized) {
            $found = true;
            break;
        }
    }
    if (! $found) {
        $missing[] = $key;
    }
}

echo 'Laravel routes: '.count($laravelPaths).PHP_EOL;
echo 'Postman requests: '.count($postmanPaths).PHP_EOL;
echo 'Missing from Postman: '.count($missing).PHP_EOL;
if ($missing) {
    foreach (array_slice($missing, 0, 30) as $m) {
        echo "  - $m\n";
    }
}
