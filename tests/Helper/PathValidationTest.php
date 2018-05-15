<?php declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Helper\Validation;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class PathValidationTest extends TestCase
{
    /**
     * @return void
     */
    public function testPathRestrictions()
    {
        $path = new Path([realpath(__DIR__. '/../_examples'), 'test.txt']);
        $validation = new Validation($path);

        $this->assertTrue($validation->isSave(Validation::NO_SYMLINK));
        $this->assertTrue($validation->isSave(Validation::IN_SAVEPATH));

        $path = new Path([__DIR__, '/../', '_examples', 'test.txt']);
        $validation = new Validation($path);

        $this->assertTrue($validation->isSave(Validation::NO_SYMLINK));
        $this->assertFalse($validation->isSave(Validation::IN_SAVEPATH));

        $path = new Path([__FILE__]);
        $validation = new Validation($path);

        $this->assertTrue($validation->isSave(Validation::NO_SYMLINK | Validation::IN_SAVEPATH));
    }
}
