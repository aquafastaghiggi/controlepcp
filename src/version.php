<?php

declare(strict_types=1);

if (!defined('APP_BUILD_VERSION')) {
    // Base semântica da build. O sufixo Git abaixo atualiza automaticamente.
    define('APP_BUILD_VERSION', '2026.05.04.2');
}

if (!function_exists('app_build_repo_root')) {
    function app_build_repo_root(): string
    {
        return dirname(__DIR__, 1);
    }
}

if (!function_exists('app_build_git_revision')) {
    function app_build_git_revision(): string
    {
        static $revision = null;
        if (is_string($revision)) {
            return $revision;
        }

        $root = app_build_repo_root();
        $revision = '';

        if (function_exists('shell_exec')) {
            $gitCmd = 'git -C ' . escapeshellarg($root);
            $head = trim((string) @shell_exec($gitCmd . ' rev-parse --short=8 HEAD 2>NUL'));
            if ($head !== '') {
                $dirty = trim((string) @shell_exec($gitCmd . ' status --porcelain 2>NUL'));
                $revision = $head . ($dirty !== '' ? '*' : '');
                return $revision;
            }
        }

        return $revision;
    }
}

if (!function_exists('app_build_version')) {
    function app_build_version(): string
    {
        return APP_BUILD_VERSION;
    }
}

if (!function_exists('app_build_label')) {
    function app_build_label(): string
    {
        $revision = app_build_git_revision();
        return 'v' . APP_BUILD_VERSION . ($revision !== '' ? ' · ' . $revision : '');
    }
}

if (!function_exists('app_build_title')) {
    function app_build_title(): string
    {
        $basePath = strtolower(__DIR__);
        if (strpos($basePath, 'controlepcp_sandbox') !== false) {
            $envLabel = 'sandbox';
        } elseif (strpos($basePath, 'controlepcp') !== false) {
            $envLabel = 'produção';
        } else {
            $env = strtolower((string) (getenv('APP_ENV') ?: ''));
            $envLabel = $env !== '' ? $env : 'local';
        }

        return 'Controle PCP · ' . $envLabel . ' · build ' . APP_BUILD_VERSION;
    }
}

if (!function_exists('render_app_build_badge')) {
    function render_app_build_badge(): string
    {
        $label = htmlspecialchars(app_build_label(), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars(app_build_title(), ENT_QUOTES, 'UTF-8');

        return '<span class="app-build-badge" title="' . $title . '">' . $label . '</span>';
    }
}
