<?php declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Auth\UserRepository;

Auth::startSession();
Auth::requireAdmin();

$repo = new UserRepository();
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Auth::requireCsrf($_POST['csrf_token'] ?? null);

    try {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'create') {
            $name = (string) ($_POST['name'] ?? '');
            $username = (string) ($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $repo->createUser($name, $username, $password);
        }

        if ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $active = (string) ($_POST['active'] ?? '0') === '1';
            if ($id > 0) {
                $repo->setActive($id, $active);
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$users = $repo->listUsers();
$me = Auth::user();

$appCssVersion = @filemtime(__DIR__ . '/assets/css/app.css') ?: 'dev';
$isSandbox = (getenv('APP_ENV') ?: '') === 'sandbox';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - Controle PCP</title>
    <link rel="stylesheet" href="assets/css/app.css?v=<?= urlencode((string) $appCssVersion) ?>">
    <link rel="stylesheet" href="assets/css/theme.css?v=<?= urlencode((string) (@filemtime(__DIR__ . '/assets/css/theme.css') ?: 'dev')) ?>">
    <style>
        .users-layout { display:grid; grid-template-columns: 1.2fr 0.8fr; gap: 20px; }
        .users-table { width: 100%; border-collapse: collapse; }
        .users-table th, .users-table td { padding: 10px 12px; border-bottom: 1px solid var(--line); vertical-align: top; }
        .users-table thead th { position: sticky; top: 0; z-index: 2; }
        .users-actions { display:flex; gap: 10px; align-items:center; flex-wrap:wrap; }
        .pill { display:inline-flex; align-items:center; gap:6px; padding: 2px 10px; border-radius: 999px; font-weight:700; font-size: 0.78rem; background: rgba(22, 42, 166, 0.08); color: var(--primary-strong); }
        .pill.is-off { background: rgba(169, 71, 50, 0.12); color: var(--danger); }
        .users-form { display:grid; gap: 10px; }
        .users-form label span { display:block; font-weight:700; margin-bottom: 6px; }
        .users-form .form-actions { margin-top: 8px; justify-content:flex-end; }
        @media (max-width: 980px) { .users-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body<?= $isSandbox ? ' data-app-env="sandbox"' : '' ?>>
    <div class="app-shell">
        <header class="hero">
            <div class="hero-copy">
                <img src="/controlepcp/logo.jpg" alt="Aqua Fast" class="hero-logo">
                <nav class="top-nav" aria-label="Navegação principal">
                    <a class="nav-link" href="index.php">Painel Inicial</a>
                    <a class="nav-link" href="logout.php">Sair</a>
                    <?php if ($me): ?>
                        <span class="pill">Logado: <?= htmlspecialchars($me['username'] ?? '', ENT_QUOTES) ?></span>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <main class="layout layout-single">
            <section class="workspace">
                <section class="panel">
                    <div class="panel-heading panel-heading-stack">
                        <div>
                            <h1>Usuários</h1>
                            <p>Cadastro de acessos (sem restrição de permissões nesta etapa).</p>
                        </div>
                    </div>

                    <?php if ($error !== ''): ?>
                        <p style="color:#b91c1c;font-weight:700;"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
                    <?php endif; ?>

                    <div class="users-layout">
                        <div class="table-wrap">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Usuário</th>
                                        <th>Status</th>
                                        <th>Criado em</th>
                                        <th>Último login</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <?php
                                            $id = (int) $user['usr_id'];
                                            $active = (int) $user['usr_active'] === 1;
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) $user['usr_name'], ENT_QUOTES) ?></td>
                                            <td><?= htmlspecialchars((string) $user['usr_username'], ENT_QUOTES) ?></td>
                                            <td>
                                                <span class="pill<?= $active ? '' : ' is-off' ?>"><?= $active ? 'Ativo' : 'Inativo' ?></span>
                                            </td>
                                            <td><?= htmlspecialchars((string) $user['usr_created_at'], ENT_QUOTES) ?></td>
                                            <td><?= htmlspecialchars((string) ($user['usr_last_login_at'] ?? ''), ENT_QUOTES) ?></td>
                                            <td style="white-space:nowrap;">
                                                <form method="post" class="users-actions">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES) ?>">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?= $id ?>">
                                                    <input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>">
                                                    <button type="submit" class="ghost-button" style="min-height:36px; padding:0 14px;">
                                                        <?= $active ? 'Desativar' : 'Ativar' ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <section class="panel">
                            <div class="panel-heading">
                                <div>
                                    <h2>Novo usuário</h2>
                                    <p>Cria um novo acesso para o sistema.</p>
                                </div>
                            </div>

                            <form method="post" class="users-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken(), ENT_QUOTES) ?>">
                                <input type="hidden" name="action" value="create">

                                <label>
                                    <span>Nome</span>
                                    <input type="text" name="name" required>
                                </label>

                                <label>
                                    <span>Usuário</span>
                                    <input type="text" name="username" required>
                                </label>

                                <label>
                                    <span>Senha</span>
                                    <input type="password" name="password" required>
                                </label>

                                <div class="form-actions">
                                    <button type="submit" class="primary-button">Criar</button>
                                </div>
                            </form>
                        </section>
                    </div>
                </section>
            </section>
        </main>
    </div>

</body>
</html>
