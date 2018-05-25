<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\Directory;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Directory\Command;
use ricwein\FileSystem\Helper\Constraint;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class CommandTest extends TestCase
{

    /**
     * @return void
     */
    public function testLsCommand()
    {
        $ls = new Command(
            new Storage\Disk(__DIR__, '../_examples'),
            Constraint::STRICT & ~Constraint::IN_SAFEPATH,
            ['/bin/ls', '/usr/local/bin/ls']
        );

        $result = $ls->execSave();
        $this->assertNotSame(false, $result);

        $files = explode(PHP_EOL, $result);
        foreach ($ls->list(false) as $file) {
            if ($file instanceof File) {
                $this->assertContains($file->path()->basename, $files);
            }
        }
    }

    /**
     * @return void
     */
    public function testGitCommand()
    {
        $git = new Command(
            new Storage\Disk(__DIR__, '../../'),
            Constraint::STRICT & ~Constraint::IN_SAFEPATH,
            ['/usr/local/bin/git', '/usr/bin/git']
        );

        $this->assertNotSame(false, $git->execSave('rev-parse --abbrev-ref HEAD')); // branch
        $this->assertNotSame(false, $git->execSave('rev-parse HEAD')); // git-rev
    }

    /**
     * @expectedException \ricwein\FileSystem\Exceptions\FileNotFoundException
     * @return void
     */
    public function testCommandMissing()
    {
        $command = new Command(
            new Storage\Disk(__DIR__, '../../'),
            Constraint::STRICT & ~Constraint::IN_SAFEPATH,
            ['/', new Path(['/']), new Storage\Disk('/'), new File(new Storage\Disk('/'))]
        );
    }
}
