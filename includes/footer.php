<?php
/**
 * includes/footer.php — GLOBAL
 * Reutilizável em TODAS as páginas (incluindo home.php quando quiseres).
 * Agora carrega TODAS as redes sociais dinamicamente da BD.
 */
if (!isset($pdo)) {
    require_once __DIR__ . '/db.php';
}
if (!function_exists('e')) {
    require_once __DIR__ . '/functions.php';
}
if (!isset($cfg)) {
    $cfg = fn(string $k, string $d = '') => get_config($pdo, $k, $d);
}

// Carregar TODAS as redes sociais configuradas
$allSocials = [];
$socialKeys = [
    'github_url', 'linkedin_url', 'whatsapp_url', 'twitter_url',
    'instagram_url', 'youtube_url', 'behance_url', 'dribbble_url',
    'medium_url', 'devto_url', 'facebook_url', 'telegram_url',
    'tiktok_url', 'discord_url', 'stackoverflow_url', 'codepen_url',
];

foreach ($socialKeys as $key) {
    $url = $cfg($key);
    if (!empty($url)) {
        $allSocials[$key] = $url;
    }
}

$email    = $cfg('email_contact');
$whatsapp = $cfg('whatsapp_url');
$wa       = $whatsapp;
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
                <!-- Redes sociais dinâmicas -->
                <div class="socials" style="margin-top:16px">
                    <?php foreach ($allSocials as $key => $url): ?>
                    <?php
                        // Mapear chave para ícone Font Awesome
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
                        $icon = $iconMap[$key] ?? 'fas fa-link';
                        ?>
                    <a href="<?= e($url) ?>" target="_blank" class="social-link"
                        title="<?= e(ucfirst(str_replace('_url', '', $key))) ?>">
                        <i class="<?= $icon ?>"></i>
                    </a>
                    <?php endforeach; ?>
                    <?php if (!empty($email)): ?>
                    <a href="mailto:<?= e($email) ?>" class="social-link" title="Email">
                        <i class="fas fa-envelope"></i>
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
                    <li><a href="<?= BASE_URL ?>/contact" class="footer-link">Contacto</a></li>
                </ul>
            </div>

            <div>
                <h4 class="footer-title">Serviços</h4>
                <ul class="footer-links">
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
                    <?php
                        $labelMap = [
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
                        $label = $labelMap[$key] ?? ucfirst(str_replace('_url', '', $key));
                        $icon = $iconMap[$key] ?? 'fas fa-link';
                        ?>
                    <li><a href="<?= e($url) ?>" target="_blank" class="footer-link"><i class="<?= $icon ?>"
                                style="width:16px"></i> <?= e($label) ?></a></li>
                    <?php endforeach; ?>
                    <?php if (!empty($email)): ?>
                    <li><a href="mailto:<?= e($email) ?>" class="footer-link"><i class="fas fa-envelope"
                                style="width:16px"></i> Email</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-copy">
                &copy; <?= date('Y') ?> José Mbenga Da Costa. Todos os direitos reservados.
            </div>
            <div class="footer-newsletter">
                <input type="email" id="newsletterEmail" placeholder="Receba novidades por email">
                <button type="button" id="newsletterBtn">Inscrever</button>
            </div>
        </div>
    </div>
</footer>

<!-- Botão voltar ao topo -->
<button id="back-top" title="Voltar ao topo" aria-label="Voltar ao topo">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- Chatbot flutuante -->
<div class="jm-chatbot" id="jm-chatbot" data-api-url="<?= BASE_URL ?>/api/chat-api.php">
    <section class="jm-chatbot-panel" id="jm-chatbot-panel" aria-labelledby="jm-chatbot-title" aria-hidden="true">
        <header class="jm-chatbot-header">
            <div class="jm-chatbot-avatar" aria-hidden="true">
                <i class="fas fa-robot"></i>
            </div>
            <div>
                <h3 id="jm-chatbot-title">Assistente JMbenga</h3>
                <p><span></span> Online agora</p>
            </div>
            <button type="button" class="jm-chatbot-close" id="jm-chatbot-close" aria-label="Fechar chat">
                <i class="fas fa-xmark"></i>
            </button>
        </header>

        <div class="jm-chatbot-messages" id="jm-chatbot-messages" role="log" aria-live="polite">
            <div class="jm-chatbot-message bot">Olá! Sou o assistente virtual do portfolio JMbenga.
                Posso ajudar com serviços, projetos, contacto ou dúvidas rápidas.
            </div>
        </div>

        <form class="jm-chatbot-form" id="jm-chatbot-form" autocomplete="off">
            <textarea id="jm-chatbot-input" name="message" rows="1" maxlength="1200"
                placeholder="Escreva a sua pergunta..." aria-label="Mensagem para o assistente"></textarea>
            <button type="submit" id="jm-chatbot-send" aria-label="Enviar mensagem">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </section>

    <button type="button" class="jm-chatbot-toggle" id="jm-chatbot-toggle" aria-controls="jm-chatbot-panel"
        aria-expanded="false" aria-label="Abrir chat">
        <i class="fas fa-robot"></i>
        <span class="jm-chatbot-dot" aria-hidden="true"></span>
    </button>
</div>

<!-- Container de toasts -->
<div id="toast-container"></div>

<!-- ═══════ SCRIPTS GLOBAIS ═══════════════════════════════════════════ -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/home.js"></script>
<script src="<?= BASE_URL ?>/assets/js/contact-form.js"></script>
<script src="<?= BASE_URL ?>/assets/js/visitor-tracker.js"></script>
<script src="<?= BASE_URL ?>/assets/js/pwa-install.js"></script>
<script src="<?= BASE_URL ?>/assets/js/pwa-native.js"></script>
<script src="<?= BASE_URL ?>/assets/js/chatbot-widget.js"></script>