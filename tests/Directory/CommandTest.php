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
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Directory\Command;
use ricwein\FileSystem\Helper\Constraint;

class CommandTest extends TestCase
{
    /**
     * @throws FileNotFoundException
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testLsCommand(): void
    {
        $ls = new Command(
            new Storage\Disk(__DIR__, '../_examples'),
            Constraint::STRICT & ~Constraint::IN_SAFEPATH,
            ['/bin/ls', '/usr/local/bin/ls']
        );

        $result = $ls->execSafe();
        self::assertNotFalse($result);

        $files = explode(PHP_EOL, $result);
        foreach ($ls->list(false)->all() as $file) {
            if ($file instanceof File) {
                self::assertContains($file->path()->filename, $files);
            }
        }
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testGitCommand(): void
    {
        $git = new Command(
            new Storage\Disk(__DIR__, '../../'),
            Constraint::STRICT & ~Constraint::IN_SAFEPATH,
            ['/usr/local/bin/git', '/usr/bin/git']
        );

        self::assertNotFalse($git->execSafe('rev-parse --abbrev-ref HEAD')); // branch
        self::assertNotFalse($git->execSafe('rev-parse HEAD')); // git-rev
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testCommandMissing(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessageMatches('/unable to find binary in paths.*/');

        new Command(
            new Storage\Disk(__DIR__, '../../'),
            Constraint::STRICT & ~Constraint::IN_SAFEPATH,
            ['/', new Path(['/']), new Storage\Disk('/'), new File(new Storage\Disk('/'))]
        );
    }

    /**
     * @throws AccessDeniedException
     * @throws UnsupportedException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws ConstraintsException
     * @throws UnexpectedValueException
     */
    public function testCommandPrepend(): void
    {
        $ls = new Command(
            new Storage\Disk(__DIR__, '../_examples'),
            Constraint::STRICT & ~Constraint::IN_SAFEPATH,
            ['/bin/ls', '/usr/local/bin/ls']
        );

        $ls->exec('TEST=test $(bin)', prependBinary: false);
        self::assertSame('TEST=test /bin/ls', $ls->getLastCommand());

        $this->expectException(RuntimeException::class);
        $ls->exec('TEST=test $(bin)', prependBinary: true);
        self::assertSame('/bin/ls TEST=test', $ls->getLastCommand());
    }
}
