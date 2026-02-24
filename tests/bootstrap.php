<?php

declare(strict_types=1);

if (!function_exists('config')) {
    $GLOBALS['__test_config'] = [];

    function config($key = null, $default = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $GLOBALS['__test_config'][$k] = $v;
            }
            return true;
        }

        if ($key === null) {
            return $GLOBALS['__test_config'];
        }

        return $GLOBALS['__test_config'][$key] ?? $default;
    }
}

if (!function_exists('app')) {
    function app(string $class)
    {
        return new $class();
    }
}

if (!function_exists('abort')) {
    function abort(int $code, string $message = '')
    {
        throw new RuntimeException($message ?: 'Aborted.', $code);
    }
}

if (!function_exists('request')) {
    function request()
    {
        return new class {
            public function input(string $key)
            {
                return null;
            }
        };
    }
}
