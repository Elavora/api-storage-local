<?php

declare(strict_types=1);

use Elavora\Api\Extension\StorageLocal\LocalStorage;
use PHPUnit\Framework\TestCase;

final class LocalStorageAtomicWriteTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        $this->rootPath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'api-storage-local-atomic-'
            . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->removePath($this->rootPath);
    }

    public function testKeepsOldContentVisibleUntilAtomicReplacement(): void
    {
        (new LocalStorage($this->rootPath))->put('reports/example.txt', 'old-content');
        $observedBeforeRename = null;
        $storage = new LocalStorage(
            $this->rootPath,
            static function (string $temporaryPath, string $destinationPath) use (
                &$observedBeforeRename
            ): bool {
                $observedBeforeRename = file_get_contents($destinationPath);

                return rename($temporaryPath, $destinationPath);
            }
        );

        $result = $storage->put('reports/example.txt', 'new-content');

        self::assertSame('old-content', $observedBeforeRename);
        self::assertSame(
            ['Key' => 'reports/example.txt', 'ContentLength' => 11],
            $result
        );
        self::assertSame('new-content', $storage->get('reports/example.txt')['Body']);
        self::assertSame([], $this->temporaryFiles());
    }

    public function testFailedReplacementPreservesOldContentAndRemovesTemporaryFile(): void
    {
        (new LocalStorage($this->rootPath))->put('reports/example.txt', 'old-content');
        $storage = new LocalStorage(
            $this->rootPath,
            static fn (string $temporaryPath, string $destinationPath): bool => false
        );

        try {
            $storage->put('reports/example.txt', 'new-content');
            self::fail('A falha na substituicao deveria lancar RuntimeException.');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }

        self::assertSame('old-content', $storage->get('reports/example.txt')['Body']);
        self::assertSame([], $this->temporaryFiles());
    }

    /**
     * @return list<string>
     */
    private function temporaryFiles(): array
    {
        $files = glob(
            $this->rootPath . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . '.elavora-storage-*'
        );

        return $files === false ? [] : $files;
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
