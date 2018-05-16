<?php declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\Path;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class PathConstraintTest extends TestCase
{
    /**
     * @return void
     */
    public function testPathRestrictions()
    {
        $path = new Path([realpath(__DIR__. '/../_examples'), 'test.txt']);
        $validation = new Constraint($path);

        $this->assertTrue($validation->doesSatisfy(Constraint::DISALLOW_LINK));
        $this->assertTrue($validation->doesSatisfy(Constraint::IN_SAVEPATH));

        $path = new Path([__DIR__, '/../', '_examples', 'test.txt']);
        $validation = new Constraint($path);

        $this->assertTrue($validation->doesSatisfy(Constraint::DISALLOW_LINK));
        $this->assertFalse($validation->doesSatisfy(Constraint::IN_SAVEPATH));

        $path = new Path([__FILE__]);
        $validation = new Constraint($path);

        $this->assertTrue($validation->doesSatisfy(Constraint::DISALLOW_LINK | Constraint::IN_SAVEPATH));
    }
}
