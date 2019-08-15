<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Storage;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Helper\MimeType;
use ricwein\FileSystem\Helper\Constraint;

/**
 * test Temp-Storage
 *
 * @author Richard Weinhold
 */
class MemoryResourceTest extends TestCase
{
    /**
     * @return void
     */
    public function testResourceRead()
    {
        $file = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        $resource = fopen($file->path()->real, 'r');
        $memory = new File(new Storage\Memory\Resource($resource));
        fclose($resource);
        $this->assertSame($file->read(), $memory->read());

        $dest = $memory->copyTo(new Storage\Disk\Temp());
        $this->assertSame($dest->read(), $memory->read());
    }

    /**
     * @return void
     */
    public function testResourceGD()
    {
        $resource = imagecreatetruecolor(1024, 1024);
        $memory = new File(new Storage\Memory\Resource($resource));

        $this->assertNotEmpty($memory->read());

        $this->assertSame($memory->getType(), MimeType::getMimeFor('png'));
    }
}
