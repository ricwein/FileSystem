<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Helper\Constraint;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class ReadTest extends TestCase
{
    /**
     * @return void
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws \Exception
     */
    public function testFileRead()
    {
        $file = new File(new Storage\Disk(__DIR__ . '/../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $this->assertSame(
            $file->read(),
            file_get_contents(__DIR__ . '/../_examples/test.txt')
        );

        $message = bin2hex(random_bytes(2 ** 10));
        $file = new File(new Storage\Memory($message));
        $this->assertSame(
            $file->read(),
            $message
        );
    }

    /**
     * @throws AccessDeniedException
     * @throws Exception
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws \Exception
     */
    public function testLineRead()
    {
        $file = new File(new Storage\Disk(__DIR__ . '/../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $this->assertSame(
            $file->readAsLines(),
            file(__DIR__ . '/../_examples/test.txt')
        );

        $message = bin2hex(random_bytes(2 ** 10));
        $message = chunk_split($message, 8, PHP_EOL);

        $file = new File(new Storage\Memory($message));
        $this->assertSame(
            $file->readAsLines(),
            explode(PHP_EOL, $message)
        );
    }

    /**
     * @return void
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws \Exception
     */
    public function testPartialFileRead()
    {
        $file = new File(new Storage\Disk(__DIR__ . '/../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        $length = (int)floor($file->getSize() / 2);
        $this->assertTrue(!empty($length));
        $this->assertSame(
            $file->read(0, $length),
            mb_substr(file_get_contents(__DIR__ . '/../_examples/test.txt'), 0, $length)
        );

        $message = bin2hex(random_bytes(2 ** 10));
        $file = new File(new Storage\Memory($message));

        $length = (int)floor($file->getSize() / 2);
        $this->assertTrue(!empty($length));
        $this->assertSame(
            $file->read(0, $length),
            mb_substr($message, 0, $length)
        );
    }

    /**
     * @return void
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testFileMimeTypeGuessing()
    {
        $file = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $this->assertSame($file->getType(), 'text/plain');

        $file = new File(new Storage\Disk(__DIR__, '../_examples', 'test.json'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $this->assertSame($file->getType(), 'application/json');
    }
}
