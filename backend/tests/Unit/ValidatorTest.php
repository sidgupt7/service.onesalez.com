<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Utils\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testValidInputIsReturned(): void
    {
        $input = ['email' => 'person@example.com', 'status' => 'ACTIVE'];
        $result = (new Validator())->validate($input, [
            'email' => ['required', 'email'],
            'status' => [['in' => ['ACTIVE', 'SUSPENDED']]],
        ]);
        self::assertSame($input, $result);
    }

    public function testValidationReportsFieldErrors(): void
    {
        try {
            (new Validator())->validate(['email' => 'bad'], ['email' => ['email'], 'name' => ['required']]);
            self::fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('email', $exception->details);
            self::assertArrayHasKey('name', $exception->details);
            self::assertSame(422, $exception->statusCode);
        }
    }
}
