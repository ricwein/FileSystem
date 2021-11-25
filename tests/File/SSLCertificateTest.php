<?php

namespace ricwein\FileSystem\Tests\File;

use DateTime;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

class SSLCertificateTest extends TestCase
{
    /**
     * @throws AccessDeniedException
     * @throws UnsupportedException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testCertificateFileReading(): void
    {
        $cert = new File\SSLCertificate(new Storage\Disk(__DIR__ . '/../_examples', 'certificate.crt'));

        self::assertSame(['test.com'], $cert->getValidDomains());
        self::assertSame('FileSystem Test', $cert->getIssuerName());

        self::assertSame(DateTime::createFromFormat('Y.m.d H:i:s', '2021.11.25 11:36:13', new DateTimeZone('UTC'))->getTimestamp(), $cert->validFrom()->getTimestamp());
        self::assertSame(DateTime::createFromFormat('Y.m.d H:i:s', '2021.11.25 11:36:13', new DateTimeZone('UTC'))->add(new \DateInterval('P365D'))->getTimestamp(), $cert->validTo()->getTimestamp());
    }

    /**
     * @throws FileNotFoundException
     * @throws UnsupportedException
     * @throws AccessDeniedException
     * @throws RuntimeException
     * @throws Exception
     */
    public function testCertificateServerFetching(): void
    {
        $cert = new File\SSLCertificate(new Storage\Memory('github.com'));

        self::assertContains('github.com', $cert->getValidDomains());

        self::assertTrue($cert->isValid());
        self::assertTrue($cert->isValidFor('github.com'));
        self::assertFalse($cert->isValidFor('google.com'));
    }
}
