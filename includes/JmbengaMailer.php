<?php
/**
 * JMbenga Portfolio — SMTP Mailer
 * Compatível com PHP 8.2+, Gmail STARTTLS, SMTPS e servidores SMTP compatíveis.
 * Não requer Composer nem bibliotecas externas.
 *
 * Segurança: não regista palavras-passe, dados AUTH, conteúdo da mensagem ou
 * endereços SMTP nos logs de debug.
 */

namespace JMbenga;

class MailerException extends \RuntimeException {}

class Mailer
{
    // Configuração SMTP. Se config.php já estiver carregado, os valores MAIL_*
    // são importados automaticamente; as propriedades continuam editáveis.
    public string $host = 'smtp.gmail.com';
    public int $port = 587;
    public string $secure = 'tls'; // 'tls' (STARTTLS) ou 'ssl' (SMTPS)
    public string $username = '';
    public string $password = '';
    public string $fromEmail = '';
    public string $fromName = '';
    public int $timeout = 15;
    public int $debug = 0; // 0=off; 1/2=diagnóstico sanitizado

    private string $toEmail = '';
    private string $toName = '';
    private string $subject = '';
    private string $bodyHtml = '';
    private string $bodyText = '';
    private string $errorInfo = '';

    /** @var resource|null */
    private $socket = null;

    public function __construct()
    {
        $this->host = $this->configString('MAIL_HOST', $this->host);
        $this->port = $this->configInt('MAIL_PORT', $this->port);
        $this->secure = strtolower($this->configString('MAIL_SECURE', $this->secure));
        $this->username = $this->configString('MAIL_USER', $this->username);
        $this->password = $this->configString('MAIL_PASS', $this->password);
        $this->fromEmail = $this->configString('MAIL_FROM', $this->fromEmail);
        $this->fromName = $this->configString('MAIL_FROM_NAME', $this->fromName);
    }

    private function configString(string $constant, string $default): string
    {
        return \defined($constant) ? (string) \constant($constant) : $default;
    }

    private function configInt(string $constant, int $default): int
    {
        if (!\defined($constant)) {
            return $default;
        }
        $value = \filter_var(\constant($constant), FILTER_VALIDATE_INT);
        return $value === false ? $default : (int) $value;
    }

    public function setFrom(string $email, string $name = ''): self
    {
        $this->fromEmail = $email;
        $this->fromName = $name;
        return $this;
    }

    public function addAddress(string $email, string $name = ''): self
    {
        $this->toEmail = $email;
        $this->toName = $name;
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
        $this->bodyText = $text !== '' ? $text : \trim(\strip_tags($html));
        return $this;
    }

    public function getErrorInfo(): string
    {
        return $this->errorInfo;
    }

    public function send(): bool
    {
        $this->errorInfo = '';

        try {
            $this->validateConfiguration();
            $this->connect();
            $this->ehlo();
            $this->startTls();
            if ($this->secure === 'tls') {
                $this->ehlo(); // Após STARTTLS, o EHLO deve ser repetido.
            }
            $this->authenticate();
            $this->sendMail();
            $this->quit();
            return true;
        } catch (MailerException $e) {
            $this->errorInfo = $e->getMessage();
            $this->close();
            throw $e;
        } catch (\Throwable $e) {
            $this->errorInfo = 'Falha inesperada durante o envio SMTP.';
            $this->close();
            throw new MailerException($this->errorInfo, 0, $e);
        }
    }

    private function validateConfiguration(): void
    {
        if (!\extension_loaded('openssl')) {
            throw new MailerException('A extensão OpenSSL não está activa no PHP.');
        }
        if ($this->host === '' || \preg_match('/^[A-Za-z0-9.-]+$/D', $this->host) !== 1) {
            throw new MailerException('O host SMTP é inválido.');
        }
        if ($this->port < 1 || $this->port > 65535) {
            throw new MailerException('A porta SMTP é inválida.');
        }
        if (!\in_array($this->secure, ['tls', 'ssl'], true)) {
            throw new MailerException('A segurança SMTP deve ser tls ou ssl. O envio sem TLS não é permitido.');
        }
        if ($this->timeout < 1 || $this->timeout > 120) {
            throw new MailerException('O timeout SMTP deve estar entre 1 e 120 segundos.');
        }
        if ($this->username === '' || $this->password === '') {
            throw new MailerException('As credenciais SMTP não estão configuradas.');
        }
        if (!\filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new MailerException('O endereço de remetente é inválido.');
        }
        if (!\filter_var($this->toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new MailerException('O endereço do destinatário é inválido.');
        }

        foreach ([
            'nome do remetente' => $this->fromName,
            'nome do destinatário' => $this->toName,
            'assunto' => $this->subject,
        ] as $label => $value) {
            if (\preg_match('/[\r\n]/', $value) === 1) {
                throw new MailerException('O ' . $label . ' contém caracteres inválidos.');
            }
        }
    }

    private function connect(): void
    {
        $sslOptions = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'peer_name' => $this->host,
            'SNI_enabled' => true,
            'disable_compression' => true,
        ];
        $context = \stream_context_create(['ssl' => $sslOptions]);
        $scheme = $this->secure === 'ssl' ? 'ssl://' : '';
        $address = $scheme . $this->host . ':' . $this->port;

        $errNo = 0;
        $errStr = '';
        $this->socket = @\stream_socket_client(
            $address,
            $errNo,
            $errStr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!\is_resource($this->socket)) {
            $detail = $errStr !== '' ? ' (' . $errStr . ')' : '';
            throw new MailerException(
                'Não foi possível ligar ao servidor SMTP na porta configurada. Código: ' . $errNo . $detail
            );
        }

        if (!@\stream_set_timeout($this->socket, $this->timeout)) {
            throw new MailerException('Não foi possível configurar o timeout da ligação SMTP.');
        }

        $resp = $this->readResponse();
        if (!$this->isCode($resp, 220)) {
            throw new MailerException('O servidor SMTP não aceitou a ligação inicial: ' . $resp);
        }
    }

    private function ehlo(): void
    {
        $host = \gethostname() ?: 'localhost';
        $host = \preg_replace('/[^A-Za-z0-9.-]/', '', $host) ?: 'localhost';
        $this->command('EHLO ' . $host, 250, 'EHLO');
    }

    private function startTls(): void
    {
        if ($this->secure !== 'tls') {
            return;
        }

        $this->command('STARTTLS', 220, 'STARTTLS');

        // Exigir TLS 1.2 ou superior; não recuar para TLS 1.0/1.1.
        $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        $result = @\stream_socket_enable_crypto($this->socket, true, $cryptoMethod);
        if ($result !== true) {
            throw new MailerException('Falha ao activar TLS 1.2. Verifica a configuração TLS do servidor SMTP e do OpenSSL.');
        }
        $this->debug('TLS activado.');
    }

    private function authenticate(): void
    {
        $this->command('AUTH LOGIN', 334, 'AUTH LOGIN [dados ocultos]');
        $this->command(\base64_encode($this->username), 334, 'AUTH username [oculto]');
        $this->command(\base64_encode($this->password), 235, 'AUTH password [oculto]');
        $this->debug('Autenticação SMTP concluída.');
    }

    private function sendMail(): void
    {
        $this->command('MAIL FROM:<' . $this->fromEmail . '>', 250, 'MAIL FROM [endereço oculto]');
        $this->command('RCPT TO:<' . $this->toEmail . '>', 250, 'RCPT TO [endereço oculto]');
        $this->command('DATA', 354, 'DATA');

        $message = $this->buildMessage();
        // SMTP dot-stuffing: duplicar qualquer ponto no início de uma linha.
        $message = \preg_replace('/(?m)^\./', '..', $message) ?? $message;

        // O corpo contém conteúdo privado; nunca o registar no debug.
        $this->write($message . "\r\n.", true, false);
        $resp = $this->readResponse();
        if (!$this->isCode($resp, 250)) {
            throw new MailerException('O servidor SMTP não aceitou a mensagem: ' . $resp);
        }
        $this->debug('Mensagem aceite pelo servidor SMTP.');
    }

    private function quit(): void
    {
        try {
            if (\is_resource($this->socket)) {
                $this->write('QUIT', true, false);
                // O e-mail já foi aceite; uma falha no QUIT não deve marcar
                // erroneamente o envio como falhado.
                $this->readResponse();
            }
        } catch (\Throwable $e) {
            $this->debug('O fecho SMTP terminou sem confirmação final.');
        } finally {
            $this->close();
        }
    }

    private function buildMessage(): string
    {
        $boundary = '----=_Jmbenga_' . \bin2hex(\random_bytes(16));
        $date = \date('r');
        $domain = \substr((string) \strrchr($this->fromEmail, '@'), 1);
        $messageId = '<' . \bin2hex(\random_bytes(16)) . '@' . $domain . '>';

        $from = $this->fromName !== ''
            ? '=?UTF-8?B?' . \base64_encode($this->fromName) . '?= <' . $this->fromEmail . '>'
            : $this->fromEmail;
        $to = $this->toName !== ''
            ? '=?UTF-8?B?' . \base64_encode($this->toName) . '?= <' . $this->toEmail . '>'
            : $this->toEmail;
        $subjectEncoded = '=?UTF-8?B?' . \base64_encode($this->subject) . '?=';

        $headers  = 'Date: ' . $date . "\r\n";
        $headers .= 'Message-ID: ' . $messageId . "\r\n";
        $headers .= 'From: ' . $from . "\r\n";
        $headers .= 'To: ' . $to . "\r\n";
        $headers .= 'Subject: ' . $subjectEncoded . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
        $headers .= "X-Mailer: Jmbenga/2.1\r\n";

        $body  = '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= \chunk_split(\base64_encode($this->bodyText)) . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= \chunk_split(\base64_encode($this->bodyHtml)) . "\r\n";
        $body .= '--' . $boundary . '--';

        return $headers . "\r\n" . $body;
    }

    private function command(string $cmd, int $expectedCode, ?string $safeLabel = null): string
    {
        if (\preg_match('/[\r\n]/', $cmd) === 1) {
            throw new MailerException('Foi detectado um comando SMTP inválido.');
        }

        $label = $safeLabel ?? $this->safeCommandLabel($cmd);
        $this->write($cmd, true, false);
        $this->debug('> ' . $label);

        $resp = $this->readResponse();
        if (!$this->isCode($resp, $expectedCode)) {
            throw new MailerException(
                'O comando SMTP ' . $label . ' falhou. Código esperado: ' . $expectedCode . '; resposta: ' . $resp
            );
        }
        return $resp;
    }

    private function safeCommandLabel(string $cmd): string
    {
        $verb = \strtoupper((string) \strtok($cmd, ' '));
        return match ($verb) {
            'MAIL' => 'MAIL FROM [endereço oculto]',
            'RCPT' => 'RCPT TO [endereço oculto]',
            'AUTH' => 'AUTH [dados ocultos]',
            default => $verb,
        };
    }

    private function write(string $data, bool $appendCrlf = true, bool $log = true): void
    {
        if (!\is_resource($this->socket)) {
            throw new MailerException('A ligação SMTP não está activa.');
        }

        if ($log) {
            $this->debug('> ' . $this->safeCommandLabel($data));
        }

        $wireData = $data . ($appendCrlf ? "\r\n" : '');
        $length = \strlen($wireData);
        $offset = 0;

        while ($offset < $length) {
            $written = @\fwrite($this->socket, \substr($wireData, $offset));
            if ($written === false || $written === 0) {
                throw new MailerException('Falha ao escrever dados na ligação SMTP.');
            }
            $offset += $written;
        }
    }

    private function readResponse(): string
    {
        if (!\is_resource($this->socket)) {
            throw new MailerException('Não existe uma ligação SMTP para ler a resposta.');
        }

        $response = '';
        while (true) {
            $line = @\fgets($this->socket, 515);
            if ($line === false) {
                $meta = \stream_get_meta_data($this->socket);
                if (!empty($meta['timed_out'])) {
                    throw new MailerException('O servidor SMTP excedeu o tempo de resposta.');
                }
                throw new MailerException('A ligação SMTP foi interrompida antes da resposta completa.');
            }

            $response .= $line;
            if (\strlen($response) > 65536) {
                throw new MailerException('A resposta do servidor SMTP excedeu o tamanho permitido.');
            }

            if (\preg_match('/^\d{3} /', $line) === 1) {
                break;
            }
        }

        $status = \preg_match('/^(\d{3})/', \trim($response), $match) === 1
            ? $match[1]
            : 'desconhecido';
        $this->debug('< Resposta SMTP recebida (código ' . $status . ').');

        return \trim($response);
    }

    private function isCode(string $response, int $code): bool
    {
        return \preg_match('/^' . \preg_quote((string) $code, '/') . '(?:[ -]|$)/', \ltrim($response)) === 1;
    }

    private function close(): void
    {
        if (\is_resource($this->socket)) {
            @\fclose($this->socket);
        }
        $this->socket = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function debug(string $message): void
    {
        if ($this->debug > 0) {
            \error_log('[JmbengaMailer] ' . $message);
        }
    }
}