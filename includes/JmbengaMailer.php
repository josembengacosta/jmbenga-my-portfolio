<?php
// ══════════════════════════════════════════════════════
// Arquivo: authentic/include/JmbengaMailer.php
//
// Suporta: Gmail (TLS/587), Outlook, qualquer SMTP com STARTTLS
// Não requer PHPMailer, Composer, nem extensões extras além de openssl
// ══════════════════════════════════════════════════════

namespace Jmbenga;

class MailerException extends \RuntimeException {}

class Mailer
{
    // ─── Configuração ─────────────────────────────────
    public string $host     = 'smtp.gmail.com';
    public int    $port     = 587;
    public string $secure   = 'tls';      // 'tls' (STARTTLS) | 'ssl' (SMTPS)
    public string $username = '';
    public string $password = '';
    public string $fromEmail = '';
    public string $fromName  = '';
    public int    $timeout   = 15;
    public int    $debug     = 0;          // 0=off | 1=commands | 2=verbose

    // ─── Mensagem ─────────────────────────────────────
    private string $toEmail   = '';
    private string $toName    = '';
    private string $subject   = '';
    private string $bodyHtml  = '';
    private string $bodyText  = '';
    private string $errorInfo = '';

    /** @var resource|null */
    private $socket = null;

    // ─── API pública ──────────────────────────────────

    public function setFrom(string $email, string $name = ''): self
    {
        $this->fromEmail = $email;
        $this->fromName  = $name;
        return $this;
    }

    public function addAddress(string $email, string $name = ''): self
    {
        $this->toEmail = $email;
        $this->toName  = $name;
        return $this;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function setBody(string $html, string $text = ''): self
    {
        $this->bodyHtml = $html;
        $this->bodyText = $text ?: strip_tags($html);
        return $this;
    }

    public function getErrorInfo(): string
    {
        return $this->errorInfo;
    }

    /**
     * Enviar o e-mail.
     * Lança MailerException em caso de erro (igual ao PHPMailer com exceptions).
     */
    public function send(): bool
    {
        try {
            $this->connect();
            $this->ehlo();
            $this->startTls();
            $this->ehlo();        // segundo EHLO após STARTTLS
            $this->authenticate();
            $this->sendMail();
            $this->quit();
            return true;
        } catch (MailerException $e) {
            $this->errorInfo = $e->getMessage();
            $this->close();
            throw $e;
        }
    }

    // ─── Passos SMTP ──────────────────────────────────

    private function connect(): void
    {
        $prefix  = ($this->secure === 'ssl') ? 'ssl://' : '';
        $address = $prefix . $this->host;

        $this->debug("Connecting to $address:{$this->port}");

        $errNo  = 0;
        $errStr = '';
        $this->socket = @stream_socket_client(
            "$address:{$this->port}",
            $errNo,
            $errStr,
            $this->timeout
        );

        if (!$this->socket) {
            throw new MailerException("Não foi possível ligar ao servidor SMTP {$this->host}:{$this->port} — $errStr ($errNo)");
        }

        stream_set_timeout($this->socket, $this->timeout);
        $resp = $this->readResponse();
        if (!$this->isCode($resp, 220)) {
            throw new MailerException("SMTP greeting falhou: $resp");
        }
    }

    private function ehlo(): void
    {
        $host = $_SERVER['SERVER_NAME'] ?? gethostname() ?: 'jmbenga.freedev.app';
        $resp = $this->command("EHLO $host", 250);
        $this->debug("EHLO response: $resp");
    }

    private function startTls(): void
    {
        if ($this->secure !== 'tls') return;

        $this->command('STARTTLS', 220);

        // Activar TLS no socket
        $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        if (!stream_socket_enable_crypto($this->socket, true, $crypto)) {
            // Fallback para TLS 1.1/1.0
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (!stream_socket_enable_crypto($this->socket, true, $crypto)) {
                throw new MailerException('Falha ao activar TLS. Verifica se a extensão openssl está activa no PHP.');
            }
        }
        $this->debug('TLS activated');
    }

    private function authenticate(): void
    {
        $this->command('AUTH LOGIN', 334);
        $this->command(base64_encode($this->username), 334);
        $resp = $this->command(base64_encode($this->password), 235);
        $this->debug("AUTH OK: $resp");
    }

    private function sendMail(): void
    {
        $from = $this->fromEmail;
        $to   = $this->toEmail;

        $this->command("MAIL FROM:<$from>", 250);
        $this->command("RCPT TO:<$to>",   250);
        $this->command('DATA',             354);

        $message = $this->buildMessage();
        $this->write($message . "\r\n.");
        $resp = $this->readResponse();
        if (!$this->isCode($resp, 250)) {
            throw new MailerException("DATA falhou: $resp");
        }
        $this->debug("Message accepted: $resp");
    }

    private function quit(): void
    {
        $this->write('QUIT');
        $this->readResponse(); // 221, mas não é crítico
        $this->close();
    }

    // ─── Construir mensagem RFC 2822 ──────────────────

    private function buildMessage(): string
    {
        $boundary = '----JmbengaBoundary_' . md5(uniqid('', true));
        $date     = date('r');
        $msgId    = '<' . uniqid('Jmbenga', true) . '@' . ($this->fromEmail ? explode('@', $this->fromEmail)[1] : 'josembengadacosta.com') . '>';

        $from = $this->fromName
            ? '=?UTF-8?B?' . base64_encode($this->fromName) . '?= <' . $this->fromEmail . '>'
            : $this->fromEmail;

        $to = $this->toName
            ? '=?UTF-8?B?' . base64_encode($this->toName) . '?= <' . $this->toEmail . '>'
            : $this->toEmail;

        $subjectEncoded = '=?UTF-8?B?' . base64_encode($this->subject) . '?=';

        $headers  = "Date: $date\r\n";
        $headers .= "Message-ID: $msgId\r\n";
        $headers .= "From: $from\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subjectEncoded\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $headers .= "X-Mailer: Jmbenga/2.0\r\n";

        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($this->bodyText)) . "\r\n";

        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($this->bodyHtml)) . "\r\n";

        $body .= "--$boundary--";

        // Escapar linhas que começam com ponto (RFC 2821 dot-stuffing)
        $body = preg_replace('/^\.$/m', '..', $body);

        return $headers . "\r\n" . $body;
    }

    // ─── Primitivas de I/O ────────────────────────────

    private function command(string $cmd, int $expectedCode): string
    {
        $this->write($cmd);
        $resp = $this->readResponse();
        if (!$this->isCode($resp, $expectedCode)) {
            throw new MailerException("Comando '$cmd' falhou. Esperado $expectedCode, recebido: $resp");
        }
        return $resp;
    }

    private function write(string $data): void
    {
        $this->debug("> $data");
        if (!$this->socket) throw new MailerException('Socket não está ligado.');
        fwrite($this->socket, $data . "\r\n");
    }

    private function readResponse(): string
    {
        $response = '';
        while (!feof($this->socket)) {
            $line = fgets($this->socket, 515);
            $this->debug("< $line");
            $response .= $line;
            // Linha final: código + espaço (não hífen)
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return trim($response);
    }

    private function isCode(string $response, int $code): bool
    {
        return str_starts_with(trim($response), (string)$code);
    }

    private function close(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function debug(string $msg): void
    {
        if ($this->debug > 0) {
            error_log('[JmbengaMailer] ' . $msg);
        }
    }
}