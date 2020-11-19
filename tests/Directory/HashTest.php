<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Directory;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Helper\Constraint;

class HashTest extends TestCase
{

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testHashCalculation(): void
    {
        $dirA = new Directory(new Storage\Disk(__DIR__, '../_examples'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $dirB = new Directory(new Storage\Disk(__DIR__, '../_examples'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        self::assertSame($dirA->getHash(), $dirB->getHash());
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testHashComparison(): void
    {
        $dirA = new Directory(new Storage\Disk(__DIR__));
        $dirB = new Directory(new Storage\Disk(__DIR__, '../_examples'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        self::assertNotSame($dirA->getHash(), $dirB->getHash());
    }
}
