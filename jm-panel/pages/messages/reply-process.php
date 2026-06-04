<?php
// ══════════════════════════════════════════════════════════════
// JMbenga Portfolio — Processador Responder Mensagem (v3)
// ══════════════════════════════════════════════════════════════

// Garantir JSON SEMPRE - header ANTES de qualquer lógica
header('Content-Type: application/json; charset=utf-8');

// Capturar output buffer para evitar HTML
ob_start();

try {
    require_once __DIR__ . '/../../include/functions_admin.php';

    // ══════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════

    function getTableColumns(PDO $db, string $table): array
    {
        try {
            return $db->query("DESCRIBE `{$table}`")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (PDOException) {
            return [];
        }
    }

    function buildEmailHtml(string $body, string $senderName, string $recipientName): string
{
    $bodyHtml = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
    $year     = date('Y');
    $siteUrl  = defined('BASE_URL') ? BASE_URL : '';
    $siteName = defined('APP_NAME') ? constant('APP_NAME') : 'JMbenga Portfolio';
    $initials = mb_substr($senderName, 0, 1);
    $currentYear = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="dark">
<title>{$siteName}</title>
</head>
<body style="margin:0;padding:0;background:#0b1121;font-family:'Segoe UI',Arial,sans-serif;-webkit-font-smoothing:antialiased">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#0b1121;padding:48px 16px">
  <tr>
    <td align="center">

      <!-- ═══ CARD PRINCIPAL ═══════════════════════════ -->
      <table width="600" cellpadding="0" cellspacing="0" role="presentation"
             style="max-width:600px;background:#111827;border-radius:20px;overflow:hidden;border:1px solid #1f2a3d;box-shadow:0 8px 32px rgba(0,0,0,.4)">

        <!-- Cabeçalho com avatar + nome -->
        <tr>
          <td style="background:linear-gradient(135deg,#2563eb 0%,#7c3aed 100%);padding:36px 40px;text-align:center">
            <table cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 16px">
              <tr>
                <td style="width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.15);
                           font-size:24px;font-weight:800;color:#fff;text-align:center;vertical-align:middle;
                           font-family:'Syne','Segoe UI',sans-serif;letter-spacing:-.02em">
                  {$initials}
                </td>
              </tr>
            </table>
            <div style="font-size:26px;font-weight:800;color:#fff;letter-spacing:-.02em;font-family:'Syne','Segoe UI',sans-serif">
              {$siteName}
            </div>
            <div style="color:rgba(255,255,255,.65);font-size:13px;margin-top:6px;font-weight:400">
              Resposta ao teu contacto
            </div>
          </td>
        </tr>

        <!-- Corpo -->
        <tr>
          <td style="padding:36px 40px">

            <!-- Saudação -->
            <p style="color:#e2e8f0;font-size:16px;margin:0 0 24px;line-height:1.5">
              Olá <strong style="color:#fff">{$recipientName}</strong>,
            </p>

            <!-- Mensagem -->
            <div style="color:#cbd5e1;font-size:15px;line-height:1.85;margin:0 0 28px;
                        padding:24px;background:#0b1121;border-radius:12px;
                        border-left:4px solid #2563eb">
              {$bodyHtml}
            </div>

            <!-- Separador -->
            <table cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 24px;width:100%">
              <tr>
                <td style="border-top:1px solid #1e293b"></td>
              </tr>
            </table>

            <!-- Assinatura -->
            <table cellpadding="0" cellspacing="0" role="presentation">
              <tr>
                <td style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#7c3aed);
                           font-size:18px;font-weight:700;color:#fff;text-align:center;vertical-align:middle;
                           font-family:'Syne','Segoe UI',sans-serif">
                  {$initials}
                </td>
                <td style="padding-left:12px">
                  <p style="margin:0;color:#e2e8f0;font-size:14px;font-weight:600">{$senderName}</p>
                  <p style="margin:2px 0 0;color:#64748b;font-size:13px">{$siteName}</p>
                </td>
              </tr>
            </table>

          </td>
        </tr>

        <!-- Rodapé -->
        <tr>
          <td style="padding:20px 40px;border-top:1px solid #1e293b;text-align:center">
            <p style="margin:0 0 4px;color:#475569;font-size:12px">
              © {$currentYear} {$siteName} — Todos os direitos reservados
            </p>
            <a href="{$siteUrl}" style="color:#2563eb;text-decoration:none;font-size:12px;font-weight:500">
              Visitar portfólio &rarr;
            </a>
          </td>
        </tr>

      </table>

      <!-- Nota discreta -->
      <p style="margin:20px 0 0;color:#334155;font-size:11px;max-width:600px;text-align:center">
        Esta é uma resposta automática a um contacto feito através do site {$siteName}.<br>
        Se não reconheces este e-mail, por favor ignora.
      </p>

    </td>
  </tr>
</table>
</body>
</html>
HTML;
}

    // ══════════════════════════════════════════════════════════════
    // INICIAR SESSÃO E VERIFICAR ACESSO
    // ══════════════════════════════════════════════════════════════

    startAdminSession();

    // VERIFICAR LOGIN (sem redirect, JSON only)
    if (!isAdminLoggedIn()) {
        http_response_code(401);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
        exit;
    }

    // VERIFICAR LOCKSCREEN
    if (function_exists('isLockscreenActive') && isLockscreenActive()) {
        http_response_code(423);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Painel bloqueado.']);
        exit;
    }

    $db = $GLOBALS['pdo'];

    // ── Validar método ────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
        exit;
    }

    // ── Validar CSRF ──────────────────────────────────────────────
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateAdminCsrf($token)) {
        http_response_code(403);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
        exit;
    }

    // ── Extrair campos ────────────────────────────────────────────
    $id = (int) ($_POST['id_message'] ?? 0);
    $replySubject = trim($_POST['reply_subject'] ?? '');
    $replyBody = trim($_POST['reply_body'] ?? '');

    // ── Validações ────────────────────────────────────────────────
    if ($id <= 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        exit;
    }
    if (empty($replySubject)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Assunto obrigatório.']);
        exit;
    }
    if (empty($replyBody) || mb_strlen($replyBody) < 10) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Resposta muito curta (mín. 10 caracteres).']);
        exit;
    }

    // ── Buscar mensagem ───────────────────────────────────────────
    $stmt = $db->prepare("SELECT * FROM _contact_message WHERE id = ?");
    $stmt->execute([$id]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$msg) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Mensagem não encontrada.']);
        exit;
    }

    // ── Dados do admin ────────────────────────────────────────────
    $adminId = (int) $_SESSION['admin_id'];
    $adminName = $_SESSION['admin_full_name'] ?? $_SESSION['admin_name'] ?? 'Admin';
    $adminEmail = $_SESSION['admin_email'] ?? (defined('MAIL_FROM') ? MAIL_FROM : '');

    // ── Enviar email ──────────────────────────────────────────────
    $sentViaEmail = false;
    $mailError = '';

    try {
        require_once __DIR__ . '/../../../includes/JmbengaMailer.php';
        

        if (class_exists('\Jmbenga\Mailer')) {
            $mailer = new \Jmbenga\Mailer();
            $mailer->host = MAIL_HOST;
            $mailer->port = MAIL_PORT;
            $mailer->secure = MAIL_SECURE;
            $mailer->username = MAIL_USER;
            $mailer->password = MAIL_PASS;
            $mailer->debug = (APP_ENV === 'development') ? 1 : 0;

            $htmlBody = buildEmailHtml($replyBody, $adminName, $msg['name_msg']);

            $mailer->setFrom($adminEmail, $adminName)
                ->addAddress($msg['email_msg'], $msg['name_msg'])
                ->setSubject($replySubject)
                ->setBody($htmlBody, $replyBody)
                ->send();

            $sentViaEmail = true;
        }
    } catch (\Throwable $e) {
        $mailError = $e->getMessage();
        error_log('[REPLY MAIL] ' . $mailError);
    }

    // ── Guardar na BD ─────────────────────────────────────────────
    $existingCols = getTableColumns($db, '_contact_message');
    $updateFields = ['status_msg = ?'];
    $updateParams = ['replied'];

    if (in_array('replied_at', $existingCols)) $updateFields[] = 'replied_at = NOW()';
    if (in_array('reply_subject', $existingCols)) {
        $updateFields[] = 'reply_subject = ?';
        $updateParams[] = $replySubject;
    }
    if (in_array('reply_body', $existingCols)) {
        $updateFields[] = 'reply_body = ?';
        $updateParams[] = $replyBody;
    }
    if (in_array('replied_by', $existingCols)) {
        $updateFields[] = 'replied_by = ?';
        $updateParams[] = $adminId;
    }
    $updateParams[] = $id;

    $db->prepare('UPDATE _contact_message SET ' . implode(', ', $updateFields) . ' WHERE id = ?')
        ->execute($updateParams);

    // ── Auditoria ─────────────────────────────────────────────────
    if (function_exists('logAudit')) {
        logAudit(
            $adminId,
            null,
            'message.reply',
            '_contact_message',
            $id,
            json_encode(['status' => $msg['status_msg']]),
            json_encode(['status' => 'replied', 'sent_email' => $sentViaEmail])
        );
    }

    // ── Responder ─────────────────────────────────────────────────
    ob_end_clean();

    echo json_encode([
        'success' => true,
        'sent_via_email' => $sentViaEmail,
        'message' => $sentViaEmail
            ? 'Resposta enviada com sucesso!'
            : 'Guardada, mas email não foi entregue.',
        'mail_error' => APP_ENV === 'development' && $mailError ? $mailError : null,
    ]);
    exit;
} catch (\Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    error_log('[REPLY] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar resposta.',
        'debug' => APP_ENV === 'development' ? $e->getMessage() : null,
    ]);
    exit;
}