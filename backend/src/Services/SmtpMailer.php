<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class SmtpMailer
{
    public function __construct(private readonly array $config)
    {
    }

    public function message(string $recipient, string $subject, string $body): PHPMailer
    {
        if ($this->config['username'] === '' || $this->config['password'] === '') {
            throw new RuntimeException('SMTP credentials are not configured.');
        }
        if (!in_array($this->config['encryption'], ['ssl', 'tls'], true)) {
            throw new RuntimeException('SMTP requires ssl or tls encryption.');
        }
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->config['host'];
        $mail->Port = $this->config['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $this->config['username'];
        $mail->Password = $this->config['password'];
        $mail->SMTPSecure = $this->config['encryption'] === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout = 20;
        $mail->getSMTPInstance()->Timelimit = 30;
        $mail->SMTPDebug = 0;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($this->config['from'], $this->config['from_name']);
        $mail->addAddress($recipient);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);
        return $mail;
    }

    public function send(string $recipient, string $subject, string $body): void
    {
        $this->message($recipient, $subject, $body)->send();
    }
}
