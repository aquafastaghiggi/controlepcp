<?php declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Auth\UserRepository;

Auth::startSession();

$repo = new UserRepository();
$hasUsers = $repo->countUsers() > 0;

$error = '';
$mode = $hasUsers ? 'login' : 'bootstrap';

if ($hasUsers && isset($_GET['register']) && (string) $_GET['register'] === '1') {
    $mode = 'register';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Auth::requireCsrf($_POST['csrf_token'] ?? null);

    try {
        $action = (string) ($_POST['action'] ?? '');

        if (!$hasUsers) {
            $name = (string) ($_POST['name'] ?? '');
            $username = (string) ($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');

            $id = $repo->createUser($name, $username, $password);
            $repo->updateLastLogin($id);

            Auth::regenerateSessionId();
            Auth::setUser(['id' => $id, 'name' => $name, 'username' => $username]);
            Auth::redirect('index.php');
        }

        if ($action === 'register') {
            $name = (string) ($_POST['name'] ?? '');
            $username = (string) ($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');

            $id = $repo->createUser($name, $username, $password);
            $repo->updateLastLogin($id);

            Auth::regenerateSessionId();
            Auth::setUser(['id' => $id, 'name' => $name, 'username' => $username]);
            Auth::redirect('index.php');
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $user = $repo->findActiveByUsername($username);
        if (!$user || !password_verify($password, (string) ($user['usr_password_hash'] ?? ''))) {
            throw new RuntimeException('Usuário ou senha inválidos.');
        }

        $id = (int) $user['usr_id'];
        $repo->updateLastLogin($id);

        Auth::regenerateSessionId();
        Auth::setUser([
            'id' => $id,
            'name' => (string) $user['usr_name'],
            'username' => (string) $user['usr_username'],
        ]);

        Auth::redirect('index.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$appCssVersion = @filemtime(__DIR__ . '/assets/css/app.css') ?: 'dev';
$isSandbox = (getenv('APP_ENV') ?: '') === 'sandbox';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Controle PCP</title>
    <link rel="stylesheet" href="/controlepcp/assets/css/app.css?v=<?= urlencode((string) $appCssVersion) ?>">
    <link rel="stylesheet" href="/controlepcp/assets/css/theme.css?v=11">
    <style>
        .auth-wrap { min-height: calc(100vh - 48px); display:flex; align-items:center; justify-content:center; }
        .auth-panel { width: min(520px, calc(100% - 32px)); }
        .auth-title { margin: 0 0 6px; }
        .auth-sub { margin: 0 0 14px; color: var(--muted); }
        .auth-switch { margin-top: 10px; font-size: 0.92rem; color: var(--muted); }
        .auth-switch a { color: var(--primary-strong); font-weight: 700; text-decoration: none; }
        .auth-switch a:hover { text-decoration: underline; }
        .auth-error { margin: 12px 0 0; color: #b91c1c; font-weight: 700; }
        .auth-form { display:grid; gap: 12px; }
        .auth-form label span { display:block; font-weight:700; margin-bottom:6px; }
        .auth-actions { display:flex; justify-content:flex-end; gap: 10px; margin-top: 8px; }
    </style>
</head>
<body<?= $isSandbox ? ' data-app-env="sandbox"' : '' ?>>
    <div class="app-shell">
        <div class="auth-wrap">
            <section class="panel auth-panel">
                <div class="panel-heading">
                    <div>
                        <h1 class="auth-title">
                            <?= $mode === 'bootstrap'
                                ? 'Criar primeiro usuário'
                                : ($mode === 'register' ? 'Criar meu usuário' : 'Entrar') ?>
                        </h1>
                        <p class="auth-sub">
                            <?= $mode === 'bootstrap'
                                ? 'Defina o primeiro acesso do sistema.'
                                : ($mode === 'register'
                                    ? 'Primeiro acesso: crie seu usuário para entrar.'
                                    : 'Faça login para acessar o Controle PCP.') ?>
                        </p>
                    </div>
                </div>

                <form method="post" class="auth-form" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES) ?>">
                    <?php if ($mode === 'register'): ?>
                        <input type="hidden" name="action" value="register">
                    <?php endif; ?>

                    <?php if ($mode === 'bootstrap' || $mode === 'register'): ?>
                        <label>
                            <span>Nome</span>
                            <input type="text" name="name" required>
                        </label>
                    <?php endif; ?>

                    <label>
                        <span>Usuário</span>
                        <input type="text" name="username" required>
                    </label>

                    <label>
                        <span>Senha</span>
                        <input type="password" name="password" required>
                    </label>

                    <div class="auth-actions">
                        <button type="submit" class="primary-button">
                            <?= $mode === 'bootstrap'
                                ? 'Criar e entrar'
                                : ($mode === 'register' ? 'Criar e entrar' : 'Entrar') ?>
                        </button>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="auth-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
                    <?php endif; ?>

                    <?php if ($hasUsers): ?>
                        <div class="auth-switch">
                            <?php if ($mode === 'register'): ?>
                                <a href="login.php">Voltar para login</a>
                            <?php else: ?>
                                Primeiro acesso? <a href="login.php?register=1">Criar meu usuário</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </form>
            </section>
        </div>
    </div>

</body>
</html>
