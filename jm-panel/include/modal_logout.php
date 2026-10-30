<?php
// JMbenga Portfolio — modal de confirmação de saída do ADMIN.

$modalAdminName = (string) ($adminName ?? $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin');
$modalAdminEmail = (string) ($adminEmail ?? $_SESSION['admin_email'] ?? '');
$modalAdminRole = (string) ($adminRole ?? $_SESSION['admin_role'] ?? 'Administrador');

if (trim($modalAdminName) === '') {
    $modalAdminName = 'Admin';
}

if (isset($adminInitial) && is_string($adminInitial) && $adminInitial !== '') {
    $modalAdminInitial = $adminInitial;
} else {
    $firstCharacter = function_exists('mb_substr')
        ? mb_substr($modalAdminName, 0, 1, 'UTF-8')
        : substr($modalAdminName, 0, 1);
    $modalAdminInitial = function_exists('mb_strtoupper')
        ? mb_strtoupper($firstCharacter, 'UTF-8')
        : strtoupper($firstCharacter);
}

$photoCandidate = $adminPhoto ?? $_SESSION['admin_photo'] ?? null;
$modalAdminPhoto = null;
if (is_string($photoCandidate) && $photoCandidate !== '') {
    // Aceita apenas um nome de ficheiro, nunca um caminho fornecido pela sessão/BD.
    if (
        basename($photoCandidate) === $photoCandidate
        && $photoCandidate !== '.'
        && $photoCandidate !== '..'
        && preg_match('/\A[a-zA-Z0-9._-]+\z/', $photoCandidate) === 1
    ) {
        $modalAdminPhoto = $photoCandidate;
    }
}

// REMOTE_ADDR é usado deliberadamente; cabeçalhos X-Forwarded-For não são confiáveis por si só.
$modalClientIP = (string) ($clientIP ?? $_SERVER['REMOTE_ADDR'] ?? '-');
$modalClientUA = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

$modalBrowser = isset($browser) && is_string($browser)
    ? $browser
    : (function_exists('parseBrowserFromUA') ? parseBrowserFromUA($modalClientUA) : 'Desconhecido');
$modalOS = isset($os) && is_string($os)
    ? $os
    : (function_exists('parseOSFromUA') ? parseOSFromUA($modalClientUA) : 'Desconhecido');

if (isset($sessionMins) && is_numeric($sessionMins)) {
    $modalSessionMins = max(0, (int) $sessionMins);
} else {
    $loginTimestamp = filter_var($_SESSION['admin_login_time'] ?? null, FILTER_VALIDATE_INT);
    $loginTimestamp = ($loginTimestamp !== false && $loginTimestamp !== null && $loginTimestamp > 0)
        ? $loginTimestamp
        : time();
    $modalSessionMins = max(0, (int) floor((time() - $loginTimestamp) / 60));
}
?>
<div class="modal-overlay" id="logoutModal">
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle">
        <div class="modal-header">
            <h2 id="logoutModalTitle">
                <i class="fas fa-sign-out-alt" style="color:var(--danger);margin-right:.5rem" aria-hidden="true"></i>
                Terminar Sessão
            </h2>
            <button type="button" class="modal-close" id="closeModal" aria-label="Fechar janela">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body">
            <div
                style="display:flex;align-items:center;gap:14px;padding:14px 16px;background:var(--bg);border-radius:var(--radius);margin-bottom:1rem">
                <div class="user-avatar" style="width:48px;height:48px;font-size:1rem">
                    <?php if ($modalAdminPhoto !== null): ?>
                    <img src="<?= e(BASE_URL) ?>/assets/img/profile/<?= rawurlencode($modalAdminPhoto) ?>" alt="">
                    <?php else: ?>
                    <?= e($modalAdminInitial) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div style="font-weight:700;font-size:.95rem"><?= e($modalAdminName) ?></div>
                    <div style="font-size:.78rem;color:var(--text-muted)"><?= e($modalAdminRole) ?> ·
                        <?= e($modalAdminEmail) ?></div>
                </div>
            </div>

            <div class="session-info">
                <div class="row">
                    <span><i class="fas fa-globe" aria-hidden="true"></i> Endereço IP</span>
                    <span><?= e($modalClientIP) ?></span>
                </div>
                <div class="row">
                    <span><i class="fas fa-window-maximize" aria-hidden="true"></i> Navegador</span>
                    <span><?= e($modalBrowser) ?></span>
                </div>
                <div class="row">
                    <span><i class="fas fa-desktop" aria-hidden="true"></i> Sistema</span>
                    <span><?= e($modalOS) ?></span>
                </div>
                <div class="row">
                    <span><i class="fas fa-clock" aria-hidden="true"></i> Tempo de sessão</span>
                    <span><?= $modalSessionMins > 0 ? e((string) $modalSessionMins) . ' min' : 'Recém iniciada' ?></span>
                </div>
            </div>

            <p style="text-align:center;font-size:.9rem;color:var(--text-dim)">
                Tens a certeza de que desejas terminar a sessão?
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cancelLogout">Cancelar</button>
            <form method="post" action="<?= e(BASE_URL) ?>/jm-panel/logout" style="display:inline;margin:0">
                <input type="hidden" name="csrf_token" value="<?= e((string) ($_SESSION['admin_csrf_token'] ?? '')) ?>">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sim, terminar sessão
                </button>
            </form>
        </div>
    </div>
</div>