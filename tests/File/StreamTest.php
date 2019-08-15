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
class StreamTest extends TestCase
{
    /**
     * @return void
     */
    public function testFileStream()
    {
        $file = new File(new Storage\Disk(__DIR__ . '/../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $this->assertSame(
            $file->read(),
            file_get_contents(__DIR__ . '/../_examples/test.txt')
        );

        ob_start();
        $file->stream();
        $content = ob_get_clean();

        $this->assertSame(
            $file->read(),
            $content
        );
    }

    /**
     * @return void
     */
    public function testMemoryStream()
    {
        $message = bin2hex(random_bytes(2 ** 10));
        $file = new File(new Storage\Memory($message));
        $this->assertSame(
            $file->read(),
            $message
        );

        ob_start();
        $file->stream();
        $content = ob_get_clean();

        $this->assertSame(
            $file->read(),
            $content
        );
    }
}
