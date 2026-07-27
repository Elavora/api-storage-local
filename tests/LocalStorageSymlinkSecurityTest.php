<?php

declare(strict_types=1);

use Elavora\Api\Extension\StorageLocal\LocalStorage;
use PHPUnit\Framework\TestCase;

final class LocalStorageSymlinkSecurityTest extends TestCase
{
    private string $basePath;
    private string $rootPath;
    private string $outsidePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'api-storage-local-security-'
            . bin2hex(random_bytes(4));
        $this->rootPath = $this->basePath . DIRECTORY_SEPARATOR . 'root';
        $this->outsidePath = $this->basePath . DIRECTORY_SEPARATOR . 'outside';

        mkdir($this->rootPath, 0775, true);
        mkdir($this->outsidePath, 0775, true);
        file_put_contents($this->outsidePath . DIRECTORY_SEPARATOR . 'proof.txt', 'protected');
    }

    protected function tearDown(): void
    {
        $this->removePath($this->basePath);
    }

    public function testRejectsDirectorySymlinkForEveryOperation(): void
    {
        $this->createSymlinkOrSkip(
            $this->outsidePath,
            $this->rootPath . DIRECTORY_SEPARATOR . 'escape'
        );
        $storage = new LocalStorage($this->rootPath);

        $this->assertSymlinkRejected(
            static fn (): array => $storage->put('escape/proof.txt', 'changed')
        );
        $this->assertSymlinkRejected(
            static fn (): array => $storage->get('escape/proof.txt')
        );
        $this->assertSymlinkRejected(
            static fn (): array => $storage->delete('escape/proof.txt')
        );
        $this->assertSymlinkRejected(
            static fn (): string => $storage->temporaryUrl('escape/proof.txt')
        );

        self::assertSame(
            'protected',
            file_get_contents($this->outsidePath . DIRECTORY_SEPARATOR . 'proof.txt')
        );
    }

    public function testRejectsFileSymlinkForReadAndWrite(): void
    {
        $outsideFile = $this->outsidePath . DIRECTORY_SEPARATOR . 'proof.txt';
        $this->createSymlinkOrSkip(
            $outsideFile,
            $this->rootPath . DIRECTORY_SEPARATOR . 'linked.txt'
        );
        $storage = new LocalStorage($this->rootPath);

        $this->assertSymlinkRejected(
            static fn (): array => $storage->put('linked.txt', 'changed')
        );
        $this->assertSymlinkRejected(
            static fn (): array => $storage->get('linked.txt')
        );

        self::assertSame('protected', file_get_contents($outsideFile));
    }

    private function assertSymlinkRejected(callable $operation): void
    {
        try {
            $operation();
            self::fail('A operacao por link simbolico deveria ser rejeitada.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
    }

    private function createSymlinkOrSkip(string $target, string $link): void
    {
        if (!function_exists('symlink') || !@symlink($target, $link)) {
            self::markTestSkipped('A plataforma nao permite criar links simbolicos.');
        }
    }

    private function removePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..') {
                $this->removePath($path . DIRECTORY_SEPARATOR . $item);
            }
        }

        @rmdir($path);
    }
}
