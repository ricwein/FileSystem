<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\Directory;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Helper\Constraint;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class HashTest extends TestCase
{

    /**
     * @return void
     */
    public function testHashCalculation()
    {
        $dirA = new Directory(new Storage\Disk(__DIR__, '../_examples'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $dirB = new Directory(new Storage\Disk(__DIR__, '../_examples'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        $this->assertSame(
            $dirA->getHash(),
            $dirB->getHash()
        );
    }

    /**
     * @return void
     */
    public function testHashComparison()
    {
        $dirA = new Directory(new Storage\Disk(__DIR__));
        $dirB = new Directory(new Storage\Disk(__DIR__, '../_examples'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        $this->assertNotSame($dirA->getHash(), $dirB->getHash());
    }
}
