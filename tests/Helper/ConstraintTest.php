<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Helper;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Path;

/**
 * @author Richard Weinhold
 */
class ConstraintTest extends TestCase
{
    public function testPathConstraintsSimple(): void
    {
        // safe-path
        $path = new Path(__FILE__);
        self::assertTrue((new Constraint(Constraint::IN_SAFEPATH | Constraint::DISALLOW_LINK))->isValidPath($path));
    }

    /**
     * @throws UnexpectedValueException
     * @throws RuntimeException
     */
    public function testPathConstraintsSafe(): void
    {
        // safe-path
        $path = new Path(realpath(__DIR__ . '/../_examples'), 'test.txt');

        self::assertTrue((new Constraint(Constraint::DISALLOW_LINK))->isValidPath($path));
        self::assertTrue((new Constraint(Constraint::IN_SAFEPATH))->isValidPath($path));
        self::assertTrue((new Constraint(Constraint::IN_SAFEPATH | Constraint::DISALLOW_LINK))->isValidPath($path));
        self::assertTrue((new Constraint(Constraint::STRICT & ~Constraint::IN_OPEN_BASEDIR))->isValidPath($path));
    }

    public function testPathConstraintsUnsafe(): void
    {
        // unsafe-path
        $path = new Path(__DIR__, '/../', '_examples', 'test.txt');

        self::assertTrue((new Constraint(Constraint::DISALLOW_LINK))->isValidPath($path));
        self::assertFalse((new Constraint(Constraint::IN_SAFEPATH))->isValidPath($path));
        self::assertFalse((new Constraint(Constraint::IN_SAFEPATH | Constraint::DISALLOW_LINK))->isValidPath($path));
        self::assertFalse((new Constraint(Constraint::STRICT & ~Constraint::IN_OPEN_BASEDIR))->isValidPath($path));
    }
}
