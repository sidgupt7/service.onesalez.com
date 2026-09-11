<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Utils\Input;
use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase
{
    public function testSanitizeTrimsAndStripsTagsRecursively(): void
    {
        self::assertSame(
            ['name' => 'Sid', 'nested' => ['value' => 'safe']],
            Input::sanitize(['name' => ' <b>Sid</b> ', 'nested' => ['value' => '<script>safe</script>']]),
        );
    }

    public function testPositiveIntegerUsesDefaultForInvalidInput(): void
    {
        self::assertSame(20, Input::positiveInt('-1', 20));
        self::assertSame(5, Input::positiveInt('5', 20));
    }
}
