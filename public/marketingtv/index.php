<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('America/Sao_Paulo');

const MARKETINGTV_DATA_FILE = __DIR__ . '/data/carousel.json';
const MARKETINGTV_UPLOAD_DIR = __DIR__ . '/uploads';
const MARKETINGTV_ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const MARKETINGTV_ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const MARKETINGTV_MAX_FILE_SIZE = 15728640; // 15 MB
const MARKETINGTV_DEFAULT_PLAYLIST = 'default';

function marketingtv_default_playlist_state(string $label = 'Padrão'): array
{
    return [
        'label' => $label,
        'duration' => 8,
        'slides' => [],
    ];
}

function marketingtv_default_root_state(): array
{
    return [
        'playlists' => [
            MARKETINGTV_DEFAULT_PLAYLIST => marketingtv_default_playlist_state(),
        ],
    ];
}

function marketingtv_ensure_storage(): void
{
    if (!is_dir(MARKETINGTV_UPLOAD_DIR)) {
        mkdir(MARKETINGTV_UPLOAD_DIR, 0777, true);
    }

    if (!file_exists(MARKETINGTV_DATA_FILE)) {
        marketingtv_save_root_state(marketingtv_default_root_state());
    }
}

function marketingtv_slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = @iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text;
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');

    return $text !== '' ? $text : 'playlist';
}

function marketingtv_normalize_slide(array $slide): array
{
    return [
        'id' => (string) ($slide['id'] ?? ''),
        'file' => (string) ($slide['file'] ?? ''),
        'original_name' => (string) ($slide['original_name'] ?? ''),
        'uploaded_at' => (string) ($slide['uploaded_at'] ?? ''),
        'hora_inicio' => marketingtv_normalize_hora($slide['hora_inicio'] ?? null),
        'hora_fim' => marketingtv_normalize_hora($slide['hora_fim'] ?? null),
    ];
}

function marketingtv_normalize_hora(mixed $valor): ?string
{
    if (!is_string($valor) || $valor === '') {
        return null;
    }

    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $valor) === 1 ? $valor : null;
}

function marketingtv_normalize_playlist(array $playlist, string $fallbackLabel): array
{
    return [
        'label' => (string) ($playlist['label'] ?? $fallbackLabel),
        'duration' => max(1, min(300, (int) ($playlist['duration'] ?? 8))),
        'slides' => array_values(array_map(
            'marketingtv_normalize_slide',
            array_filter($playlist['slides'] ?? [], static fn ($slide) => is_array($slide))
        )),
    ];
}

function marketingtv_load_root_state(): array
{
    marketingtv_ensure_storage();

    $json = @file_get_contents(MARKETINGTV_DATA_FILE);
    $data = $json !== false && trim($json) !== '' ? json_decode($json, true) : null;

    if (!is_array($data)) {
        return marketingtv_default_root_state();
    }

    // Migração do formato antigo (uma playlist só, sem chave "playlists")
    $precisaMigrar = !isset($data['playlists']);
    if ($precisaMigrar) {
        $data = [
            'playlists' => [
                MARKETINGTV_DEFAULT_PLAYLIST => [
                    'label' => 'Padrão',
                    'duration' => $data['duration'] ?? 8,
                    'slides' => $data['slides'] ?? [],
                ],
            ],
        ];
    }

    $playlists = [];
    foreach (($data['playlists'] ?? []) as $slug => $playlist) {
        if (!is_array($playlist)) {
            continue;
        }
        $playlists[(string) $slug] = marketingtv_normalize_playlist($playlist, (string) $slug);
    }

    if (empty($playlists)) {
        $playlists[MARKETINGTV_DEFAULT_PLAYLIST] = marketingtv_default_playlist_state();
    }

    $novoState = ['playlists' => $playlists];

    // Persiste a migração imediatamente — o tv.js lê o JSON direto do disco
    // (sem passar por este PHP), então o arquivo precisa já estar no formato
    // novo assim que alguém abrir o painel, não só na próxima escrita.
    if ($precisaMigrar) {
        marketingtv_save_root_state($novoState);
    }

    return $novoState;
}

function marketingtv_save_root_state(array $state): void
{
    file_put_contents(
        MARKETINGTV_DATA_FILE,
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function marketingtv_flash(string $type, string $message): void
{
    $_SESSION['marketingtv_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function marketingtv_take_flash(): ?array
{
    if (!isset($_SESSION['marketingtv_flash'])) {
        return null;
    }

    $flash = $_SESSION['marketingtv_flash'];
    unset($_SESSION['marketingtv_flash']);

    return is_array($flash) ? $flash : null;
}

function marketingtv_csrf_token(): string
{
    if (empty($_SESSION['marketingtv_csrf'])) {
        $_SESSION['marketingtv_csrf'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['marketingtv_csrf'];
}

function marketingtv_verify_csrf(?string $token): bool
{
    return is_string($token) && hash_equals((string) ($_SESSION['marketingtv_csrf'] ?? ''), $token);
}

function marketingtv_slide_filename(string $originalName, int $index): string
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $suffix = $index > 0 ? '-' . $index : '';

    return 'slide-' . date('Ymd-His') . $suffix . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
}

function marketingtv_is_valid_image(string $tmpName, string $originalName): bool
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, MARKETINGTV_ALLOWED_EXTENSIONS, true)) {
        return false;
    }

    if (!is_file($tmpName)) {
        return false;
    }

    $finfo = function_exists('finfo_open') ? new finfo(FILEINFO_MIME_TYPE) : null;
    if ($finfo instanceof finfo) {
        $mimeType = $finfo->file($tmpName);
        if (is_string($mimeType) && $mimeType !== '' && in_array($mimeType, MARKETINGTV_ALLOWED_MIME_TYPES, true)) {
            return true;
        }
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false) {
        return false;
    }

    return in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : -1], true);
}

function marketingtv_public_url(string $file): string
{
    return 'uploads/' . rawurlencode($file);
}

marketingtv_ensure_storage();
$rootState = marketingtv_load_root_state();
$flash = marketingtv_take_flash();

$requestedPlaylist = (string) ($_GET['playlist'] ?? $_POST['playlist'] ?? MARKETINGTV_DEFAULT_PLAYLIST);
$requestedPlaylist = preg_match('/^[a-z0-9-]+$/', $requestedPlaylist) === 1 ? $requestedPlaylist : MARKETINGTV_DEFAULT_PLAYLIST;
if (!isset($rootState['playlists'][$requestedPlaylist])) {
    $requestedPlaylist = array_key_first($rootState['playlists']);
}
$currentSlug = $requestedPlaylist;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!marketingtv_verify_csrf($_POST['csrf_token'] ?? null)) {
        marketingtv_flash('error', 'Sessão expirada. Recarregue a página e tente de novo.');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?playlist=' . rawurlencode($currentSlug));
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_playlist') {
        $label = trim((string) ($_POST['label'] ?? ''));
        if ($label === '') {
            marketingtv_flash('error', 'Informe um nome para a nova playlist.');
        } else {
            $slug = marketingtv_slugify($label);
            $baseSlug = $slug;
            $suffix = 2;
            while (isset($rootState['playlists'][$slug])) {
                $slug = $baseSlug . '-' . $suffix;
                $suffix++;
            }

            $rootState['playlists'][$slug] = marketingtv_default_playlist_state($label);
            marketingtv_save_root_state($rootState);
            marketingtv_flash('success', sprintf('Playlist "%s" criada.', $label));
            header('Location: ' . $_SERVER['PHP_SELF'] . '?playlist=' . rawurlencode($slug));
            exit;
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?playlist=' . rawurlencode($currentSlug));
        exit;
    }

    if ($action === 'delete_playlist') {
        if (count($rootState['playlists']) <= 1) {
            marketingtv_flash('error', 'Não é possível excluir a única playlist existente.');
        } elseif (isset($rootState['playlists'][$currentSlug])) {
            foreach ($rootState['playlists'][$currentSlug]['slides'] as $slide) {
                $path = MARKETINGTV_UPLOAD_DIR . DIRECTORY_SEPARATOR . (string) ($slide['file'] ?? '');
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            unset($rootState['playlists'][$currentSlug]);
            marketingtv_save_root_state($rootState);
            marketingtv_flash('success', 'Playlist excluída.');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?playlist=' . rawurlencode($currentSlug));
        exit;
    }

    $playlist = $rootState['playlists'][$currentSlug] ?? marketingtv_default_playlist_state();

    if ($action === 'save_duration') {
        $playlist['duration'] = max(1, min(300, (int) ($_POST['duration'] ?? $playlist['duration'] ?? 8)));
        $rootState['playlists'][$currentSlug] = $playlist;
        marketingtv_save_root_state($rootState);
        marketingtv_flash('success', 'Tempo do slide atualizado.');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?playlist=' . rawurlencode($currentSlug));
        exit;
    }

    if ($action === 'upload') {
        $playlist['duration'] = max(1, min(300, (int) ($_POST['duration'] ?? $playlist['duration'] ?? 8)));
        $horaInicio = marketingtv_normalize_hora($_POST['hora_inicio'] ?? null);
        $horaFim = marketingtv_normalize_hora($_POST['hora_fim'] ?? null);

        $uploaded = 0;
        $skipped = 0;
        $errors = [];

        if (!isset($_FILES['images'])) {
            $errors[] = 'Nenhum arquivo foi enviado.';
        } else {
            $names = $_FILES['images']['name'] ?? [];
            $tmpNames = $_FILES['images']['tmp_name'] ?? [];
            $sizes = $_FILES['images']['size'] ?? [];
            $uploadErrors = $_FILES['images']['error'] ?? [];

            if (!is_array($names)) {
                $names = [$names];
                $tmpNames = [$tmpNames];
                $sizes = [$sizes];
                $uploadErrors = [$uploadErrors];
            }

            foreach ($names as $i => $originalName) {
                $error = (int) ($uploadErrors[$i] ?? UPLOAD_ERR_NO_FILE);
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if ($error !== UPLOAD_ERR_OK) {
                    $errors[] = sprintf('Falha ao enviar "%s" (código %d).', $originalName, $error);
                    $skipped++;
                    continue;
                }

                $tmpName = (string) ($tmpNames[$i] ?? '');
                $size = (int) ($sizes[$i] ?? 0);

                if ($size <= 0 || $size > MARKETINGTV_MAX_FILE_SIZE) {
                    $errors[] = sprintf('"%s" excede o limite de 15 MB.', $originalName);
                    $skipped++;
                    continue;
                }

                if (!marketingtv_is_valid_image($tmpName, (string) $originalName)) {
                    $errors[] = sprintf('"%s" não parece ser uma imagem válida JPEG, PNG ou GIF.', $originalName);
                    $skipped++;
                    continue;
                }

                $filename = marketingtv_slide_filename((string) $originalName, $uploaded + 1);
                $destination = MARKETINGTV_UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename;

                if (!move_uploaded_file($tmpName, $destination)) {
                    $errors[] = sprintf('Não foi possível salvar "%s".', $originalName);
                    $skipped++;
                    continue;
                }

                $playlist['slides'][] = [
                    'id' => bin2hex(random_bytes(8)),
                    'file' => $filename,
                    'original_name' => (string) $originalName,
                    'uploaded_at' => date('c'),
                    'hora_inicio' => $horaInicio,
                    'hora_fim' => $horaFim,
                ];
                $uploaded++;
            }
        }

        $rootState['playlists'][$currentSlug] = $playlist;
        marketingtv_save_root_state($rootState);

        if ($uploaded > 0 && empty($errors)) {
            marketingtv_flash('success', sprintf('%d imagem(ns) enviada(s) com sucesso.', $uploaded));
        } elseif ($uploaded > 0) {
            marketingtv_flash('warning', sprintf('%d imagem(ns) enviada(s). Algumas foram ignoradas.', $uploaded));
        } else {
            marketingtv_flash('error', $errors[0] ?? 'Nenhuma imagem válida foi enviada.');
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?playlist=' . rawurlencode($currentSlug));
        exit;
    }

    if ($action === 'update_schedule') {
        $id = (string) ($_POST['id'] ?? '');
        $horaInicio = marketingtv_normalize_hora($_POST['hora_inicio'] ?? null);
        $horaFim = marketingtv_normalize_hora($_POST['hora_fim'] ?? null);
        $updated = false;

        foreach ($playlist['slides'] as $index => $slide) {
            if (($slide['id'] ?? '') === $id) {
                $playlist['slides'][$index]['hora_inicio'] = $horaInicio;
                $playlist['slides'][$index]['hora_fim'] = $horaFim;
                $updated = true;
                break;
            }
        }

        $rootState['playlists'][$currentSlug] = $playlist;
        marketingtv_save_root_state($rootState);
        marketingtv_flash($updated ? 'success' : 'warning', $updated ? 'Horário atualizado.' : 'Imagem não encontrada.');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?playlist=' . rawurlencode($currentSlug));
        exit;
    }

    if ($action === 'delete') {
        $id = (string) ($_POST['id'] ?? '');
        $deleted = false;

        foreach ($playlist['slides'] as $index => $slide) {
            if (($slide['id'] ?? '') === $id) {
                $file = (string) ($slide['file'] ?? '');
                $path = MARKETINGTV_UPLOAD_DIR . DIRECTORY_SEPARATOR . $file;
                if ($file !== '' && is_file($path)) {
                    @unlink($path);
                }
                unset($playlist['slides'][$index]);
                $deleted = true;
                break;
            }
        }

        $playlist['slides'] = array_values($playlist['slides']);
        $rootState['playlists'][$currentSlug] = $playlist;
        marketingtv_save_root_state($rootState);

        marketingtv_flash($deleted ? 'success' : 'warning', $deleted ? 'Imagem removida.' : 'Imagem não encontrada.');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?playlist=' . rawurlencode($currentSlug));
        exit;
    }

    marketingtv_flash('error', 'Ação desconhecida.');
    header('Location: ' . $_SERVER['PHP_SELF'] . '?playlist=' . rawurlencode($currentSlug));
    exit;
}

$csrfToken = marketingtv_csrf_token();
$playlists = $rootState['playlists'];
$playlist = $playlists[$currentSlug] ?? marketingtv_default_playlist_state();
$slides = $playlist['slides'];
$slideCount = count($slides);
$duration = (int) ($playlist['duration'] ?? 8);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing TV</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="app-shell admin-shell">
    <main class="panel admin-panel">
        <header class="topbar">
            <div class="brand">
                <img src="assets/logo-aquafast.svg" alt="Aquafast" class="brand-logo">
                <div>
                    <div class="eyebrow">Marketing TV</div>
                    <h1>Carrossel de imagens para a TV</h1>
                </div>
            </div>
            <a class="button button-ghost" href="tv.html?playlist=<?php echo rawurlencode($currentSlug); ?>" target="_blank" rel="noopener">Abrir tela da TV</a>
        </header>

        <?php if ($flash): ?>
            <div class="alert alert-<?php echo htmlspecialchars((string) ($flash['type'] ?? 'success'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <section class="card">
            <div class="section-heading">
                <h2>Playlists</h2>
            </div>
            <p class="muted">Cada playlist tem seu próprio link de TV — use uma playlist por TV física.</p>

            <div class="playlist-tabs">
                <?php foreach ($playlists as $slug => $pl): ?>
                    <a class="playlist-tab<?php echo $slug === $currentSlug ? ' active' : ''; ?>"
                       href="?playlist=<?php echo rawurlencode($slug); ?>">
                        <?php echo htmlspecialchars((string) $pl['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form class="inline-form playlist-create-form" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="create_playlist">
                <label>
                    Nova playlist
                    <input type="text" name="label" placeholder="Ex: Refeitório" maxlength="60" required>
                </label>
                <button type="submit" class="button button-secondary">Criar</button>
            </form>

            <div class="playlist-link-box">
                <span class="muted">Link desta playlist para a TV:</span>
                <code><?php echo htmlspecialchars('tv.html?playlist=' . $currentSlug, ENT_QUOTES, 'UTF-8'); ?></code>
            </div>

            <?php if (count($playlists) > 1): ?>
                <form method="post" onsubmit="return confirm('Excluir a playlist &quot;<?php echo htmlspecialchars((string) $playlist['label'], ENT_QUOTES, 'UTF-8'); ?>&quot; e todas as suas imagens?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="delete_playlist">
                    <input type="hidden" name="playlist" value="<?php echo htmlspecialchars($currentSlug, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="button button-danger button-small">Excluir playlist "<?php echo htmlspecialchars((string) $playlist['label'], ENT_QUOTES, 'UTF-8'); ?>"</button>
                </form>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Envio de imagens — <?php echo htmlspecialchars((string) $playlist['label'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="muted">
                Envie fotos em <strong>JPG</strong>, <strong>PNG</strong> ou <strong>GIF</strong>.
                Para TV antiga, recomendo imagens horizontais em proporção 16:9.
            </p>

            <form class="upload-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="playlist" value="<?php echo htmlspecialchars($currentSlug, ENT_QUOTES, 'UTF-8'); ?>">

                <label>
                    Tempo por slide
                    <input type="number" name="duration" min="1" max="300" value="<?php echo $duration; ?>">
                </label>

                <label>
                    Mostrar a partir de (opcional)
                    <input type="time" name="hora_inicio">
                </label>

                <label>
                    Mostrar até (opcional)
                    <input type="time" name="hora_fim">
                </label>

                <label class="file-field">
                    Imagens
                    <input type="file" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
                </label>

                <button type="submit" class="button button-primary">Enviar para a galeria</button>
            </form>
            <p class="muted">Deixe os horários em branco para a imagem ficar sempre visível. O horário se repete todo dia.</p>

            <form class="inline-form" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="save_duration">
                <input type="hidden" name="playlist" value="<?php echo htmlspecialchars($currentSlug, ENT_QUOTES, 'UTF-8'); ?>">
                <label>
                    Duração padrão da apresentação
                    <input type="number" name="duration" min="1" max="300" value="<?php echo $duration; ?>">
                </label>
                <button type="submit" class="button button-secondary">Salvar tempo</button>
            </form>
        </section>

        <section class="card">
            <div class="section-heading">
                <h2>Imagens carregadas</h2>
                <span class="counter"><?php echo $slideCount; ?> slide(s)</span>
            </div>

            <?php if ($slideCount === 0): ?>
                <div class="empty-state">
                    Nenhuma imagem enviada ainda nesta playlist. Depois do upload, a TV vai passar o carrossel automaticamente.
                </div>
            <?php else: ?>
                <div class="gallery-grid">
                    <?php foreach ($slides as $slide): ?>
                        <?php
                            $file = (string) ($slide['file'] ?? '');
                            $title = (string) ($slide['original_name'] ?? $file);
                            $horaInicio = (string) ($slide['hora_inicio'] ?? '');
                            $horaFim = (string) ($slide['hora_fim'] ?? '');
                        ?>
                        <article class="gallery-item">
                            <div class="thumb">
                                <img src="<?php echo htmlspecialchars(marketingtv_public_url($file), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="item-meta">
                                <strong><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="slide-schedule">
                                    <?php if ($horaInicio === '' && $horaFim === ''): ?>
                                        Sempre visível
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($horaInicio !== '' ? $horaInicio : '00:00', ENT_QUOTES, 'UTF-8'); ?>
                                        –
                                        <?php echo htmlspecialchars($horaFim !== '' ? $horaFim : '23:59', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <form class="inline-form schedule-form" method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="update_schedule">
                                <input type="hidden" name="playlist" value="<?php echo htmlspecialchars($currentSlug, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) ($slide['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <label>De <input type="time" name="hora_inicio" value="<?php echo htmlspecialchars($horaInicio, ENT_QUOTES, 'UTF-8'); ?>"></label>
                                <label>Até <input type="time" name="hora_fim" value="<?php echo htmlspecialchars($horaFim, ENT_QUOTES, 'UTF-8'); ?>"></label>
                                <button type="submit" class="button button-secondary button-small">Salvar horário</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Remover esta imagem?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="playlist" value="<?php echo htmlspecialchars($currentSlug, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) ($slide['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="button button-danger">Excluir</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
