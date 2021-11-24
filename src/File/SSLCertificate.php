<?php

namespace ricwein\FileSystem\File;

use DateTime;
use Exception;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Enum\Time;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Helper\Stream;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Storage\Extensions\Binary;
use function fclose;
use function openssl_x509_parse;
use function stream_context_create;
use function stream_context_get_params;
use function stream_socket_client;

class SSLCertificate extends File
{
    private ?array $certificateInfo = null;

    public function __construct(Storage $storage, int $constraints = Constraint::STRICT, private int $timeout = 30)
    {
        parent::__construct($storage, $constraints);
    }

    /**
     * @throws RuntimeException
     */
    private function parseCertificateURL(string $url): string
    {
        $scheme = 'ssl';
        $host = '';
        $port = 443;

        $components = parse_url($url);

        if (count($components) === 1 && isset($components['path'])) {
            $host = $components['path'];
        } else {
            foreach (['scheme', 'host', 'port'] as $key) {
                if (isset($components[$key])) {
                    $$key = $components[$key];
                }
            }
        }

        $url = "$scheme://$host:$port";

        if (empty($host) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException("Invalid URL or Domain provided.", 500);
        }

        return $url;
    }

    /**
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnsupportedException
     * @throws UnexpectedValueException
     * @internal
     */
    public function getCertificateInfo(): array
    {
        if (null !== $info = $this->certificateInfo) {
            return $info;
        }

        if ($this->storage instanceof Storage\Memory) {
            $content = $this->storage->readFile();

            // probably a ssl-certificate in memory, just parsing it is fine
            if (str_contains($content, "\n")) {
                if (false === $certInfo = openssl_x509_parse($content)) {
                    throw new RuntimeException("Unable to parse ssl-certificate.", 500);
                }
                $this->certificateInfo = $certInfo;
                return $certInfo;
            }

            $url = $this->parseCertificateURL($content);

            $sslContext = stream_context_create(["ssl" => ["capture_peer_cert" => TRUE]]);
            $sslStream = stream_socket_client($url, $errorCode, $errorMessage, $this->timeout, STREAM_CLIENT_CONNECT, $sslContext);
            if (false === $sslStream) {
                throw new RuntimeException("Unable to open stream to: '$url' - ERROR [$errorCode]: $errorMessage.", 500);
            }

            try {
                $certResource = stream_context_get_params($sslStream);
                $certificate = $certResource['options']['ssl']['peer_certificate'];
                if (false === $certInfo = openssl_x509_parse($certificate)) {
                    throw new RuntimeException("Unable to parse ssl-certificate for: '$url'.", 500);
                }
                $this->certificateInfo = $certInfo;
                return $certInfo;
            } finally {
                fclose($sslStream);
            }
        }

        if ($this->storage instanceof Storage\Disk) {
            if (false === $certInfo = openssl_x509_parse($this->storage->readFile())) {
                throw new RuntimeException("Unable to parse ssl-certificate for file: '{$this->getPath()}'.", 500);
            }
            $this->certificateInfo = $certInfo;
            return $certInfo;
        }

        throw new UnsupportedException("Unable to fetch ssl-certificate info from storage of type %s", get_debug_type($this->storage), 500);
    }

    /**
     * @throws FileNotFoundException
     * @throws UnsupportedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function getIssuerName(): ?string
    {
        $certInfo = $this->getCertificateInfo();
        return $certInfo['issuer']['O'] ?? null;
    }

    /**
     * @throws FileNotFoundException
     * @throws UnsupportedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function getIssuer(): array
    {
        return $this->getCertificateInfo()['issuer'] ?? [];
    }

    /**
     * @return string[]
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function getValidDomains(): array
    {
        $certInfo = $this->getCertificateInfo();

        $subjects = [];
        if (isset($certInfo['subject']['CN'])) {
            $subjects[] = $certInfo['subject']['CN'];
        }

        if (isset($certInfo['extensions']['subjectAltName'])) {
            foreach (explode(',', $certInfo['extensions']['subjectAltName']) as $subject) {
                $subject = trim($subject);
                if (str_starts_with($subject, 'DNS:')) {
                    $subject = substr($subject, 4);
                }
                $subjects[] = $subject;
            }
        }

        return array_values(array_unique($subjects));
    }

    /**
     * @throws FileNotFoundException
     * @throws UnsupportedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function isValidFor(string $url): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        $components = parse_url($url);
        if (count($components) === 1 && isset($components['path'])) {
            $url = $components['path'];
        } elseif (isset($components['host'])) {
            $url = $components['host'];
        }
        $urlNestCount = substr_count($url, '.');

        $subjects = $this->getValidDomains();

        foreach ($subjects as $subject) {
            if ($subject === $url) {
                return true;
            }

            if (!str_starts_with($subject, '*.')) {
                continue;
            }

            if ($urlNestCount !== substr_count($subject, '.')) {
                continue;
            }

            if (fnmatch($subject, $url)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws FileNotFoundException
     * @throws UnsupportedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function validFrom(): ?DateTime
    {
        $certInfo = $this->getCertificateInfo();

        if (!isset($certInfo['validFrom_time_t'])) {
            return null;
        }

        $validFrom = DateTime::createFromFormat('U', $certInfo['validFrom_time_t']);

        if ($validFrom === false) {
            return null;
        }

        return $validFrom;
    }

    /**
     * @throws FileNotFoundException
     * @throws UnsupportedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function validTo(): ?DateTime
    {
        $certInfo = $this->getCertificateInfo();

        if (!isset($certInfo['validTo_time_t'])) {
            return null;
        }

        $validTo = DateTime::createFromFormat('U', $certInfo['validTo_time_t']);

        if ($validTo === false) {
            return null;
        }

        return $validTo;
    }

    /**
     * @throws FileNotFoundException
     * @throws UnsupportedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function isValid(): bool
    {
        if ((null === $validFrom = $this->validFrom()) || (null === $validTo = $this->validTo())) {
            throw new RuntimeException("Unable to parse ssl-certificate info for validation: missing validFrom or validTo attributes.", 500);
        }

        $now = new DateTime();
        return $now >= $validFrom && $now <= $validTo;
    }

    public function getDate(int $type = Time::LAST_MODIFIED): ?DateTime
    {
        return match ($type) {
            Time::LAST_MODIFIED, Time::CREATED => $this->validFrom(),
            Time::LAST_ACCESSED => new DateTime(),
            default => null,
        };
    }

    /**
     * @throws Exception
     */
    public function getTime(int $type = Time::LAST_MODIFIED): ?int
    {
        return $this->getDate()?->getTimestamp();
    }

    /**
     * @throws UnsupportedException
     */
    public function write(string $content, bool $append = false, int $mode = LOCK_EX): static
    {
        throw new UnsupportedException("Writing a ssl-certificate is currently not supported.");
    }

    /**
     * @throws UnsupportedException
     */
    public function remove(): static
    {
        throw new UnsupportedException("Removing a ssl-certificate is currently not supported.");
    }

    /**
     * @throws UnsupportedException
     * @throws FileNotFoundException
     * @throws RuntimeException
     */
    public function path(): Path|string
    {
        if ($this->storage instanceof Storage\Memory) {
            $content = $this->storage->readFile();
            if (!str_contains($content, "\n")) {
                return $this->parseCertificateURL($content);
            }
        }

        return parent::path();
    }

    /**
     * @throws UnsupportedException
     * @throws FileNotFoundException
     * @throws RuntimeException
     */
    public function getPath(): string
    {
        if ($this->storage instanceof Storage\Memory) {
            return $this->path();
        }

        return parent::getPath();
    }

    public function getType(bool $withEncoding = false): string
    {
        return 'application/x-x509-user-cert';
    }

    /**
     * @throws FileNotFoundException
     * @throws UnsupportedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function getHash(int $mode = Hash::CONTENT, string $algo = 'sha256', bool $raw = false): string
    {
        if ($mode !== Hash::CONTENT) {
            throw new UnsupportedException('Unable to get selected hash for ssl-certificate. Only Hash::CONTENT is available.', 500);
        }

        $certInfo = $this->getCertificateInfo();
        if (!isset($certInfo['hash'])) {
            throw new RuntimeException("Unable to fetch hash from ssl-certificate.", 500);
        }

        return $raw ? hex2bin($certInfo['hash']) : $certInfo['hash'];
    }

    /**
     * @throws UnsupportedException
     */
    public function getSize(): int
    {
        throw new UnsupportedException("Calculating size of a ssl-certificate is currently not supported.");
    }

    public function touch(bool $ifNewOnly = false, ?int $time = null, ?int $atime = null): bool
    {
        throw new UnsupportedException("Touching a ssl-certificate is currently not supported.");
    }

    public function getHandle(int $mode): Binary
    {
        throw new UnsupportedException("Getting a binary file-handle of a ssl-certificate is currently not supported.");
    }

    /**
     * @throws UnsupportedException
     */
    public function getStream(string $mode = 'rb+'): Stream
    {
        throw new UnsupportedException("Getting a file-stream of a ssl-certificate is currently not supported.");
    }

    public function __toString(): string
    {
        return sprintf('x509 Certificate (%s)', $certInfo['subject']['CN'] ?? '');
    }
}
