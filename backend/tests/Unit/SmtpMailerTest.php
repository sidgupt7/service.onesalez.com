<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Services\SmtpMailer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SmtpMailerTest extends TestCase
{
    private function config(): array
    {
        return ['host' => 'smtp.example.com', 'port' => 465, 'encryption' => 'ssl',
            'username' => 'sender@example.com', 'password' => 'test-only-password',
            'from' => 'sender@example.com', 'from_name' => 'Service CRM'];
    }

    public function testResetLinkIsPreservedInAuthenticatedEncryptedMessage(): void
    {
        $body = 'Reset: https://service.example.com/reset-password?realm=employee&token=a_b-C';
        $mail = (new SmtpMailer($this->config()))->message('recipient@example.com', 'Reset password', $body);
        self::assertSame('smtp', $mail->Mailer);
        self::assertTrue($mail->SMTPAuth);
        self::assertSame('ssl', $mail->SMTPSecure);
        self::assertSame(0, $mail->SMTPDebug);
        self::assertTrue($mail->preSend());
        self::assertStringContainsString($body, $mail->getSentMIMEMessage());
        self::assertStringNotContainsString('test-only-password', $mail->getSentMIMEMessage());
    }

    public function testMissingCredentialsFailWithoutSending(): void
    {
        $config = $this->config();
        $config['password'] = '';
        $this->expectException(RuntimeException::class);
        (new SmtpMailer($config))->message('recipient@example.com', 'Reset', 'Body');
    }

    public function testUnencryptedTransportIsRejected(): void
    {
        $config = $this->config();
        $config['encryption'] = '';
        $this->expectException(RuntimeException::class);
        (new SmtpMailer($config))->message('recipient@example.com', 'Reset', 'Body');
    }
}
