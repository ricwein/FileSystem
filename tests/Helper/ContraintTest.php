<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Helper;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\Path;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class ConstraintTest extends TestCase
{
    public function testPathConstraints()
    {

        // safe-path
        $path = new Path([realpath(__DIR__ . '/../_examples'), 'test.txt']);

        $this->assertTrue((new Constraint(Constraint::DISALLOW_LINK))->isValidPath($path));
        $this->assertTrue((new Constraint(Constraint::IN_SAFEPATH))->isValidPath($path));
        $this->assertTrue((new Constraint(Constraint::IN_SAFEPATH | Constraint::DISALLOW_LINK))->isValidPath($path));
        $this->assertTrue((new Constraint(Constraint::STRICT & ~Constraint::IN_OPENBASEDIR))->isValidPath($path));

        // unsafe-path
        $path = new Path([__DIR__, '/../', '_examples', 'test.txt']);

        $this->assertTrue((new Constraint(Constraint::DISALLOW_LINK))->isValidPath($path));
        $this->assertFalse((new Constraint(Constraint::IN_SAFEPATH))->isValidPath($path));
        $this->assertFalse((new Constraint(Constraint::IN_SAFEPATH | Constraint::DISALLOW_LINK))->isValidPath($path));
        $this->assertFalse((new Constraint(Constraint::STRICT & ~Constraint::IN_OPENBASEDIR))->isValidPath($path));

        // safe-path again
        $path = new Path([__FILE__]);

        $this->assertTrue((new Constraint(Constraint::IN_SAFEPATH | Constraint::DISALLOW_LINK))->isValidPath($path));
    }
}
