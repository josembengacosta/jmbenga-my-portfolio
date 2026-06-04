<?php
require_once __DIR__ . '/include/functions_admin.php';
require_once __DIR__ . '../../includes/JmbengaMailer.php';

$mailer = new \Jmbenga\Mailer();
$mailer->host   = MAIL_HOST;
$mailer->port   = MAIL_PORT;
$mailer->secure = MAIL_SECURE;
$mailer->username = MAIL_USER;
$mailer->password = MAIL_PASS;
$mailer->debug  = 1;

try {
    $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME)
           ->addAddress('josembengadacosta@gmail.com', 'Teste')
           ->setSubject('Teste de envio')
           ->setBody('<h1>Funciona!</h1>', 'Funciona!')
           ->send();
    echo 'Email enviado!';
} catch (\Jmbenga\MailerException $e) {
    echo 'Erro: ' . $e->getMessage();
}