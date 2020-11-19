<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Helper;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\Path;

/**
 * @author Richard Weinhold
 */
class ConstraintTest extends TestCase
{
    /**
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testPathConstraints(): void
    {
        // safe-path
        $path = new Path([realpath(__DIR__ . '/../_examples'), 'test.txt']);

        self::assertTrue((new Constraint(Constraint::DISALLOW_LINK))->isValidPath($path));
        self::assertTrue((new Constraint(Constraint::IN_SAFEPATH))->isValidPath($path));
        self::assertTrue((new Constraint(Constraint::IN_SAFEPATH | Constraint::DISALLOW_LINK))->isValidPath($path));
        self::assertTrue((new Constraint(Constraint::STRICT & ~Constraint::IN_OPENBASEDIR))->isValidPath($path));

        // unsafe-path
        $path = new Path([__DIR__, '/../', '_examples', 'test.txt']);

        self::assertTrue((new Constraint(Constraint::DISALLOW_LINK))->isValidPath($path));
        self::assertFalse((new Constraint(Constraint::IN_SAFEPATH))->isValidPath($path));
        self::assertFalse((new Constraint(Constraint::IN_SAFEPATH | Constraint::DISALLOW_LINK))->isValidPath($path));
        self::assertFalse((new Constraint(Constraint::STRICT & ~Constraint::IN_OPENBASEDIR))->isValidPath($path));

        // safe-path again
        $path = new Path([__FILE__]);

        self::assertTrue((new Constraint(Constraint::IN_SAFEPATH | Constraint::DISALLOW_LINK))->isValidPath($path));
    }
}
