<?php
/*
    SceneGames
    Copyright (C) 2018  GoodOldDownloads

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/


// Get ip address
$app->add(new RKA\Middleware\IpAddress(true));

// Remove slashes
$app->add(function ($request, $response, callable $next) {
    $uri = $request->getUri();
    $path = $uri->getPath();
    if ($path != '/' && substr($path, -1) == '/') {
        $uri = $uri->withPath(substr($path, 0, -1))->withPort(null);
        if ($request->getMethod() == 'GET') {
            return $response->withRedirect((string)$uri, 301);
        } else {
            return $next($request->withUri($uri), $response);
        }
    }
    return $next($request, $response);
});

// Set headers for all requests
$app->add(function ($request, $response, $next) {
    $Config = new \GoodOldDownloads\Config();
    $nonceJS = base64_encode(random_bytes(24));
    $nonceCSS = base64_encode(random_bytes(24));
    $adminOnlyImgCSP = " img-src 'self' http://thegamesdb.net;";
    $CORS = "default-src 'none'; script-src 'self' 'nonce-$nonceJS'; style-src 'self' 'unsafe-inline'; font-src 'self'; frame-ancestors 'none'; base-uri 'self'; connect-src 'self';".(isset($_COOKIE['was_user']) ? $adminOnlyImgCSP : " img-src 'self'");

    // Add global variable to Twig
    $view = $this->get('view');
    $view->getEnvironment()->addGlobal('disable_drive_voting', boolval($Config->get('disable_drive_voting')));
    $view->getEnvironment()->addGlobal('nonce', ['script' => $nonceJS, 'style' => $nonceCSS]);

    $response = $next($request, $response);
    return $response
            ->withHeader('Content-Security-Policy', $CORS)
            ->withHeader('X-Content-Security-Policy', $CORS)
            ->withHeader('X-WebKit-CSP', $CORS)
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Follow-The-White-Rabbit', "https://www.youtube.com/watch?v=6GggY4TEYbk");
});

// Set language
$app->add(function ($request, $response, $next) {
    $CONFIG = $this->get('site_config');
    $allowedLanguages = [
        'de_DE' => [
            'ISO-639-1' => 'de'
        ],
        'es_ES' => [
            'ISO-639-1' => 'es'
        ],
        'ru_RU' => [
            'ISO-639-1' => 'ru'
        ],
        'el_GR' => [
            'ISO-639-1' => 'el'
        ],
        'ar_SA' => [
            'ISO-639-1' => 'ar'
        ],
        'et_EE' => [
            'ISO-639-1' => 'et'
        ],
        'en_CA' => [
            'ISO-639-1' => 'hodor'
        ]
    ];

    if (isset($_POST['setlang'])) {
        if (isset($_POST['setlang']) && isset($allowedLanguages[$_POST['setlang']])) { // isset() is faster than in_array()
            setcookie('language', $_POST['setlang'], time()+60*60*24*365, '/', $request->getUri()->getHost(), ($CONFIG["DEV"] ? false : true), true);
            $_COOKIE["language"] = $_POST['setlang']; // For current
        } else {
            unset($_COOKIE["language"]);
            setcookie('language', '', -1, '/', $request->getUri()->getHost());
        }
    }

    $language = null;
    if (isset($_COOKIE["language"]) && isset($allowedLanguages[$_COOKIE["language"]])) { // isset() is faster than in_array()
        $language = $_COOKIE["language"];
        putenv("LC_ALL=$language");
        setlocale(LC_ALL, $language);
        bindtextdomain('messages', '../locale');
        bind_textdomain_codeset('messages', 'UTF-8');
        textdomain('messages');
    }

    // Add global variable to Twig
    $view = $this->get('view');
    if ($language !== null) {
        $view->getEnvironment()->addGlobal('language', $allowedLanguages[$language]);
    } else {
        $view->getEnvironment()->addGlobal('language', null);
    }

    $response = $next($request, $response);
    if (isset($_POST['setlang'])) {
        return $response->withRedirect((string)$request->getUri()->withPort(null), 302);
    }
    return $response;
});