<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Utils\Input;
use App\Utils\Validator;
use PHPUnit\Framework\TestCase;

final class PasswordInputTest extends TestCase
{
    public function testSecretsArePreservedInNestedOnboardingAndResetInput(): void
    {
        $password = ' <special&Password> 123 ';
        $data = Input::sanitize(['email' => ' user@example.com ', 'password' => $password, 'administrator' => ['password' => $password], 'new_password' => $password]);
        self::assertSame($password, $data['password']);
        self::assertSame($password, $data['new_password']);
        self::assertSame($password, $data['administrator']['password']);
        self::assertSame('user@example.com', $data['email']);
    }

    public function testPasswordArraysAreRejectedBeforeAuthentication(): void
    {
        $this->expectException(ValidationException::class);
        (new Validator())->validate(['password' => ['unexpected']], ['password' => ['required']]);
    }

    public function testBcryptByteLimitIsEnforcedForMultibytePasswords(): void
    {
        $this->expectException(ValidationException::class);
        (new Validator())->validate(['password' => str_repeat('界', 25)], ['password' => ['required', ['min' => 12]]]);
    }
}
