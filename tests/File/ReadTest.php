<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
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
     * @return void
     */
    public function testPartialFileRead()
    {
        $file = new File(new Storage\Disk(__DIR__ . '/../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        $length = (int) floor($file->getSize() / 2);
        $this->assertTrue(!empty($length));
        $this->assertSame(
            $file->read(0, $length),
            mb_substr(file_get_contents(__DIR__ . '/../_examples/test.txt'), 0, $length)
        );

        $message = bin2hex(random_bytes(2 ** 10));
        $file = new File(new Storage\Memory($message));

        $length = (int) floor($file->getSize() / 2);
        $this->assertTrue(!empty($length));
        $this->assertSame(
            $file->read(0, $length),
            mb_substr($message, 0, $length)
        );
    }

    /**
     * @return void
     */
    public function testFileMimeTypeGuessing()
    {
        $file = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $this->assertSame($file->getType(), 'text/plain');

        $file = new File(new Storage\Disk(__DIR__, '../_examples', 'test.json'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $this->assertSame($file->getType(), 'application/json');
    }
}
