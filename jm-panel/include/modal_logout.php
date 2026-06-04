<?php
$modalAdminName = $adminName ?? $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
$modalAdminEmail = $adminEmail ?? $_SESSION['admin_email'] ?? '';
$modalAdminInitial = $adminInitial ?? strtoupper(mb_substr($modalAdminName, 0, 1));
$modalAdminPhoto = $adminPhoto ?? $_SESSION['admin_photo'] ?? null;
$modalClientIP = $clientIP ?? ($_SERVER['REMOTE_ADDR'] ?? '-');
$modalClientUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
$modalBrowser = $browser ?? parseBrowserFromUA($modalClientUA);
$modalOS = $os ?? parseOSFromUA($modalClientUA);
$modalSessionMins = isset($sessionMins)
    ? (int) $sessionMins
    : floor((time() - ($_SESSION['admin_login_time'] ?? time())) / 60);

$adminName = $modalAdminName;
$adminEmail = $modalAdminEmail;
$adminInitial = $modalAdminInitial;
$adminPhoto = $modalAdminPhoto;
$clientIP = $modalClientIP;
$browser = $modalBrowser;
$os = $modalOS;
$sessionMins = $modalSessionMins;
?>
<div class="modal-overlay" id="logoutModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h2><i class="fas fa-sign-out-alt" style="color:var(--danger);margin-right:.5rem"></i> Terminar Sessão
            </h2>
            <button class="modal-close" id="closeModal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <!-- Info do Admin -->
            <div
                style="display:flex;align-items:center;gap:14px;padding:14px 16px;background:var(--bg);border-radius:var(--radius);margin-bottom:1rem">
                <div class="user-avatar" style="width:48px;height:48px;font-size:1rem">
                    <?php if ($modalAdminPhoto): ?><img src="<?= BASE_URL ?>/assets/img/profile/<?= e($modalAdminPhoto) ?>"
                        alt="">
                    <?php else: ?><?= e($modalAdminInitial) ?><?php endif; ?>
                </div>
                <div>
                    <div style="font-weight:700;font-size:.95rem"><?= e($modalAdminName) ?></div>
                    <div style="font-size:.78rem;color:var(--text-muted)">Super Admin · <?= e($adminEmail) ?></div>
                </div>
            </div>
            <!-- Info da Sessão -->
            <div class="session-info">
                <div class="row"><span><i class="fas fa-globe"></i> Endereço IP</span><span><?= $clientIP ?></span>
                </div>
                <div class="row"><span><i class="fas fa-browser"></i> Navegador</span><span><?= e($modalBrowser) ?></span>
                </div>
                <div class="row"><span><i class="fas fa-desktop"></i> Sistema</span><span><?= e($modalOS) ?></span></div>
                <div class="row"><span><i class="fas fa-clock"></i> Tempo de
                        sessão</span><span><?= $sessionMins > 0 ? $sessionMins . ' min' : 'Recém iniciada' ?></span>
                </div>
            </div>
            <p style="text-align:center;font-size:.9rem;color:var(--text-dim)">Tens a certeza de que desejas
                terminar a sessão?</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="cancelLogout">Cancelar</button>
            <a href="<?= BASE_URL ?>/jm-panel/logout" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i>
                Sim, terminar sessão</a>
        </div>
    </div>
</div>
