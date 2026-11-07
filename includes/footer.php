<?php


/**
 * includes/footer.php — footer e componentes globais do JMbenga Portfolio.
 * Redes sociais e contactos são carregados a partir da configuração da aplicação.
 */
if (!isset($pdo)) {
    require_once __DIR__ . '/db.php';
}

if (!function_exists('e')) {
    require_once __DIR__ . '/functions.php';
}

if (!isset($cfg)) {
    $cfg = static fn(string $key, string $default = ''): string => get_config($pdo, $key, $default);
}

/** Aceita apenas URLs HTTP(S) válidos provenientes da configuração. */
$safeHttpUrl = static function (?string $value): string {
    $url = trim((string) $value);

    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
};

$socialLabels = [
    'github_url'        => 'GitHub',
    'linkedin_url'      => 'LinkedIn',
    'whatsapp_url'      => 'WhatsApp',
    'twitter_url'       => 'Twitter / X',
    'instagram_url'     => 'Instagram',
    'youtube_url'       => 'YouTube',
    'behance_url'       => 'Behance',
    'dribbble_url'      => 'Dribbble',
    'medium_url'        => 'Medium',
    'devto_url'         => 'Dev.to',
    'facebook_url'      => 'Facebook',
    'telegram_url'      => 'Telegram',
    'tiktok_url'        => 'TikTok',
    'discord_url'       => 'Discord',
    'stackoverflow_url' => 'Stack Overflow',
    'codepen_url'       => 'CodePen',
];

$iconMap = [
    'github_url'        => 'fab fa-github',
    'linkedin_url'      => 'fab fa-linkedin-in',
    'whatsapp_url'      => 'fab fa-whatsapp',
    'twitter_url'       => 'fab fa-x-twitter',
    'instagram_url'     => 'fab fa-instagram',
    'youtube_url'       => 'fab fa-youtube',
    'behance_url'       => 'fab fa-behance',
    'dribbble_url'      => 'fab fa-dribbble',
    'medium_url'        => 'fab fa-medium-m',
    'devto_url'         => 'fab fa-dev',
    'facebook_url'      => 'fab fa-facebook-f',
    'telegram_url'      => 'fab fa-telegram-plane',
    'tiktok_url'        => 'fab fa-tiktok',
    'discord_url'       => 'fab fa-discord',
    'stackoverflow_url' => 'fab fa-stack-overflow',
    'codepen_url'       => 'fab fa-codepen',
];

$socialKeys = array_keys($socialLabels);
$allSocials = [];

foreach ($socialKeys as $key) {
    $url = $safeHttpUrl($cfg($key));
    if ($url !== '') {
        $allSocials[$key] = $url;
    }
}

$emailCandidate = trim($cfg('email_contact'));
$email = filter_var($emailCandidate, FILTER_VALIDATE_EMAIL) !== false
    ? $emailCandidate
    : '';
?>

<!-- ════════ FOOTER ════════════════════════════════════════════ -->
<footer>
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="logo" style="margin-bottom:12px">
                    <span class="logo-accent">JM</span>benga
                </div>
                <p>Transformando ideias em experiências digitais excepcionais com código limpo e design inovador.</p>

                <!-- Redes sociais configuradas no painel -->
                <div class="socials" style="margin-top:16px">
                    <?php foreach ($allSocials as $key => $url): ?>
                    <?php $label = $socialLabels[$key]; ?>
                    <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" class="social-link"
                        title="<?= e($label) ?>" aria-label="Abrir <?= e($label) ?> em nova aba">
                        <i class="<?= e($iconMap[$key] ?? 'fas fa-link') ?>" aria-hidden="true"></i>
                    </a>
                    <?php endforeach; ?>

                    <?php if ($email !== ''): ?>
                    <a href="mailto:<?= e($email) ?>" class="social-link" title="Email"
                        aria-label="Enviar email para <?= e($email) ?>">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <h4 class="footer-title">Links Rápidos</h4>
                <ul class="footer-links">
                    <li><a href="<?= BASE_URL ?>/home" class="footer-link">Home</a></li>
                    <li><a href="<?= BASE_URL ?>/about" class="footer-link">Sobre</a></li>
                    <li><a href="<?= BASE_URL ?>/skills" class="footer-link">Skills</a></li>
                    <li><a href="<?= BASE_URL ?>/projects" class="footer-link">Projectos</a></li>
                    <li><a href="<?= BASE_URL ?>/servicos" class="footer-link">Todos os serviços</a></li>
                    <li><a href="<?= BASE_URL ?>/contact" class="footer-link">Contacto</a></li>
                </ul>
            </div>

            <div>
                <h4 class="footer-title">Serviços</h4>
                <ul class="footer-links">
                    <li><a href="<?= BASE_URL ?>/servicos" class="footer-link"><strong>Ver todos os
                                serviços</strong></a></li>
                    <li><a href="<?= BASE_URL ?>/services/web-development" class="footer-link">Desenvolvimento Web</a>
                    </li>
                    <li><a href="<?= BASE_URL ?>/services/mobile-apps" class="footer-link">Apps Mobile</a></li>
                    <li><a href="<?= BASE_URL ?>/services/api-backend" class="footer-link">APIs &amp; Backend</a></li>
                    <li><a href="<?= BASE_URL ?>/services/ui-ux-design" class="footer-link">UI/UX Design</a></li>
                    <li><a href="<?= BASE_URL ?>/services/maintenance" class="footer-link">Manutenção</a></li>
                </ul>
            </div>

            <div>
                <h4 class="footer-title">Conecte-se</h4>
                <ul class="footer-links">
                    <?php foreach ($allSocials as $key => $url): ?>
                    <?php $label = $socialLabels[$key] ?? 'Website'; ?>
                    <li>
                        <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" class="footer-link"
                            aria-label="Abrir <?= e($label) ?> em nova aba">
                            <i class="<?= e($iconMap[$key] ?? 'fas fa-link') ?>" style="width:16px"
                                aria-hidden="true"></i>
                            <?= e($label) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>

                    <?php if ($email !== ''): ?>
                    <li>
                        <a href="mailto:<?= e($email) ?>" class="footer-link">
                            <i class="fas fa-envelope" style="width:16px" aria-hidden="true"></i>
                            Email
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-copy">
                &copy; <?= date('Y') ?> José Mbenga Da Costa. Todos os direitos reservados.
            </div>
        </div>
    </div>
</footer>

<!-- Botão voltar ao topo -->
<button id="back-top" type="button" title="Voltar ao topo" aria-label="Voltar ao topo">
    <i class="fas fa-arrow-up" aria-hidden="true"></i>
</button>

<!-- Chatbot flutuante -->
<div class="jm-chatbot" id="jm-chatbot" data-api-url="<?= e(BASE_URL) ?>/api/core-sync">
    <section class="jm-chatbot-panel" id="jm-chatbot-panel" aria-labelledby="jm-chatbot-title" aria-hidden="true">
        <header class="jm-chatbot-header">
            <div class="jm-chatbot-avatar" aria-hidden="true">
                <i class="fas fa-robot"></i>
            </div>

            <div class="jm-chatbot-heading">
                <h3 id="jm-chatbot-title">JMbenga AI</h3>
                <p class="jm-chatbot-status" id="jm-chatbot-status" data-state="unknown" aria-live="polite">
                    <span class="jm-chatbot-status-dot" id="jm-chatbot-status-dot" aria-hidden="true"></span>
                    <span class="jm-chatbot-status-text" id="jm-chatbot-status-text">Disponibilidade por
                        confirmar</span>
                </p>
            </div>

            <button type="button" class="jm-chatbot-clear" id="jm-chatbot-clear" aria-label="Limpar conversa"
                title="Limpar conversa"
                style="background:transparent;color:inherit;border:0;cursor:pointer;padding:8px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-trash-can" aria-hidden="true"></i>
            </button>

            <button type="button" class="jm-chatbot-close" id="jm-chatbot-close" aria-label="Fechar chat">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <div class="jm-chatbot-messages" id="jm-chatbot-messages" role="log" aria-live="polite">
            <div class="jm-chatbot-message bot" style="font-style: italic; text-align: justify; max-width: 100%;">
                Olá! Sou o assistente virtual do JMbenga.
                <div>Posso ajudar com serviços, projectos, contacto ou dúvidas rápidas.</div>
            </div>
        </div>

        <form class="jm-chatbot-form" id="jm-chatbot-form" autocomplete="off">
            <textarea id="jm-chatbot-input" name="message" rows="1" maxlength="1200"
                placeholder="Escreva a sua pergunta..." aria-label="Mensagem para o assistente"></textarea>
            <button type="submit" id="jm-chatbot-send" aria-label="Enviar mensagem">
                <i class="fas fa-paper-plane" aria-hidden="true"></i>
            </button>
        </form>
    </section>

    <button type="button" class="jm-chatbot-toggle" id="jm-chatbot-toggle" aria-controls="jm-chatbot-panel"
        aria-expanded="false" aria-label="Abrir chat">
        <i class="fas fa-robot" aria-hidden="true"></i>
        <span class="jm-chatbot-dot" aria-hidden="true"></span>
    </button>
</div>

<!-- Container de toasts -->
<div id="toast-container" aria-live="polite" aria-atomic="true"></div>

<!-- ═══════ SCRIPTS GLOBAIS ═══════════════════════════════════════════ -->
<script src="<?= e(BASE_URL) ?>/assets/js/main.js"></script>
<script src="<?= e(BASE_URL) ?>/assets/js/home.js"></script>
<script src="<?= e(BASE_URL) ?>/assets/js/contact-form.js"></script>
<script src="<?= e(BASE_URL) ?>/assets/js/visitor-tracker.js"></script>
<script src="<?= e(BASE_URL) ?>/assets/js/pwa-install.js"></script>
<script src="<?= e(BASE_URL) ?>/assets/js/pwa-native.js"></script>
<script src="<?= e(BASE_URL) ?>/assets/js/app-util.js"></script>