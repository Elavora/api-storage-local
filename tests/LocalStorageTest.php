<?php

declare(strict_types=1);

use Elavora\Api\Extension\StorageLocal\LocalStorage;
use Elavora\Api\Extension\StorageLocal\LocalStorageExtension;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocalStorageTest extends TestCase
{
    private string $rootPath;

    protected function setUp(): void
    {
        $this->rootPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'api-storage-local-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootPath);
    }

    public function testStoresReadsAndDeletesFile(): void
    {
        $storage = new LocalStorage($this->rootPath);

        self::assertSame(
            ['Key' => 'reports/example.txt', 'ContentLength' => 7],
            $storage->put('reports/example.txt', 'content'),
        );
        self::assertSame('content', $storage->get('reports/example.txt')['Body']);
        self::assertTrue($storage->delete('reports/example.txt')['Deleted']);
        self::assertFileDoesNotExist(
            $this->rootPath . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'example.txt',
        );
    }

    public function testRejectsPathTraversal(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new LocalStorage($this->rootPath))->put('../outside.txt', 'content');
    }

    public function testRegistersStorageContract(): void
    {
        $application = Application::create()->extend(new LocalStorageExtension($this->rootPath));

        self::assertInstanceOf(Storage::class, $application->container()->get(Storage::class));
    }

    public function testRegisteredStorageIsLocalStorage(): void
    {
        $application = Application::create()->extend(new LocalStorageExtension($this->rootPath));

        self::assertInstanceOf(LocalStorage::class, $application->container()->get(Storage::class));
    }

    public function testExtensionDoesNotValidateRootPath(): void
    {
        $extension = new LocalStorageExtension('');

        self::assertInstanceOf(LocalStorageExtension::class, $extension);
    }

    #[DataProvider('emptyRootPaths')]
    public function testRejectsEmptyRootPath(string $rootPath): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O diretorio raiz do storage local e obrigatorio.');

        new LocalStorage($rootPath);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function emptyRootPaths(): array
    {
        return [
            'vazio' => [''],
            'espacos' => ['   '],
        ];
    }

    public function testRootPathDoesNotNeedToExist(): void
    {
        $storage = new LocalStorage($this->rootPath);

        self::assertDirectoryDoesNotExist($this->rootPath);
        self::assertSame(
            ['Key' => 'a.txt', 'ContentLength' => 1],
            $storage->put('a.txt', 'x'),
        );
        self::assertFileExists($this->rootPath . DIRECTORY_SEPARATOR . 'a.txt');
    }

    public function testPutCreatesNestedDirectoriesAndWritesBody(): void
    {
        $storage = new LocalStorage($this->rootPath);

        $storage->put('a/b/c/file.txt', 'abc');

        self::assertSame(
            'abc',
            file_get_contents(
                $this->rootPath . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'b'
                    . DIRECTORY_SEPARATOR . 'c' . DIRECTORY_SEPARATOR . 'file.txt',
            ),
        );
    }

    public function testPutOverwritesExistingFile(): void
    {
        $storage = new LocalStorage($this->rootPath);

        $storage->put('a.txt', 'primeiro');
        $storage->put('a.txt', 'novo');

        self::assertSame('novo', $storage->get('a.txt')['Body']);
    }

    public function testContentLengthIsMeasuredInBytes(): void
    {
        $storage = new LocalStorage($this->rootPath);

        // "ação" tem 4 caracteres e 6 bytes em UTF-8.
        self::assertSame(6, $storage->put('utf8.txt', 'ação')['ContentLength']);
        self::assertSame(
            ['Key' => 'utf8.txt', 'Body' => 'ação', 'ContentLength' => 6],
            $storage->get('utf8.txt'),
        );
    }

    public function testPutAndGetEmptyBody(): void
    {
        $storage = new LocalStorage($this->rootPath);

        self::assertSame(['Key' => 'empty.txt', 'ContentLength' => 0], $storage->put('empty.txt', ''));
        self::assertSame(
            ['Key' => 'empty.txt', 'Body' => '', 'ContentLength' => 0],
            $storage->get('empty.txt'),
        );
    }

    public function testGetReturnsKeyBodyAndContentLength(): void
    {
        $storage = new LocalStorage($this->rootPath);
        $storage->put('reports/example.txt', 'content');

        self::assertSame(
            ['Key' => 'reports/example.txt', 'Body' => 'content', 'ContentLength' => 7],
            $storage->get('reports/example.txt'),
        );
    }

    public function testGetMissingFileThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Arquivo nao encontrado no storage local.');

        (new LocalStorage($this->rootPath))->get('missing.txt');
    }

    public function testGetDirectoryThrowsRuntimeException(): void
    {
        $storage = new LocalStorage($this->rootPath);
        $storage->put('dir/file.txt', 'x');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Arquivo nao encontrado no storage local.');

        $storage->get('dir');
    }

    public function testDeleteReturnsKeyAndDeleted(): void
    {
        $storage = new LocalStorage($this->rootPath);
        $storage->put('a.txt', 'x');

        self::assertSame(['Key' => 'a.txt', 'Deleted' => true], $storage->delete('a.txt'));
    }

    public function testDeleteMissingFileIsNotAnError(): void
    {
        $storage = new LocalStorage($this->rootPath);

        self::assertSame(['Key' => 'nada.txt', 'Deleted' => true], $storage->delete('nada.txt'));
    }

    public function testDeleteTwiceIsNotAnError(): void
    {
        $storage = new LocalStorage($this->rootPath);
        $storage->put('a.txt', 'x');

        self::assertTrue($storage->delete('a.txt')['Deleted']);
        self::assertTrue($storage->delete('a.txt')['Deleted']);
    }

    public function testTemporaryUrlIsFileUrlWithFullPath(): void
    {
        $storage = new LocalStorage($this->rootPath);

        self::assertSame(
            'file://' . $this->rootPath . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'a.txt',
            $storage->temporaryUrl('reports/a.txt'),
        );
    }

    public function testTemporaryUrlIgnoresExpirationAndDoesNotNeedFile(): void
    {
        $storage = new LocalStorage($this->rootPath);
        $expected = 'file://' . $this->rootPath . DIRECTORY_SEPARATOR . 'nao-existe.txt';

        self::assertSame($expected, $storage->temporaryUrl('nao-existe.txt'));
        self::assertSame($expected, $storage->temporaryUrl('nao-existe.txt', new DateTimeImmutable('+1 hour')));
        self::assertSame($expected, $storage->temporaryUrl('nao-existe.txt', new DateTimeImmutable('-1 year')));
        self::assertDirectoryDoesNotExist($this->rootPath);
    }

    public function testRootPathTrailingSeparatorsAreIgnored(): void
    {
        $storage = new LocalStorage($this->rootPath . '/');

        self::assertSame(
            'file://' . $this->rootPath . DIRECTORY_SEPARATOR . 'a.txt',
            $storage->temporaryUrl('a.txt'),
        );
    }

    public function testOptionsAreAcceptedAndIgnored(): void
    {
        $storage = new LocalStorage($this->rootPath);
        $options = ['ContentType' => 'text/plain', 'qualquer' => 1];

        self::assertSame(['Key' => 'a.txt', 'ContentLength' => 1], $storage->put('a.txt', 'x', $options));
        self::assertSame('x', $storage->get('a.txt', $options)['Body']);
        self::assertSame(
            'file://' . $this->rootPath . DIRECTORY_SEPARATOR . 'a.txt',
            $storage->temporaryUrl('a.txt', null, $options),
        );
        self::assertTrue($storage->delete('a.txt', $options)['Deleted']);
    }

    public function testKeyWithBackslashesIsNormalized(): void
    {
        $storage = new LocalStorage($this->rootPath);

        self::assertSame(
            ['Key' => 'reports/2026/a.txt', 'ContentLength' => 1],
            $storage->put('reports\\2026\\a.txt', 'x'),
        );
        self::assertSame('x', $storage->get('reports/2026/a.txt')['Body']);
        self::assertSame('reports/2026/a.txt', $storage->get('reports\\2026\\a.txt')['Key']);
        self::assertSame('reports/2026/a.txt', $storage->delete('reports\\2026\\a.txt')['Key']);
    }

    public function testKeyIsTrimmedAtEnds(): void
    {
        $storage = new LocalStorage($this->rootPath);

        self::assertSame(
            ['Key' => 'reports/a.txt', 'ContentLength' => 1],
            $storage->put("  reports/a.txt\t\n", 'x'),
        );
        self::assertSame('reports/a.txt', $storage->get("\n reports/a.txt ")['Key']);
        self::assertSame('reports/a.txt', $storage->delete(' reports/a.txt ')['Key']);
        self::assertSame(
            'file://' . $this->rootPath . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'a.txt',
            $storage->temporaryUrl('  reports/a.txt  '),
        );
    }

    public function testNullBytesAtEndsAreTrimmed(): void
    {
        $storage = new LocalStorage($this->rootPath);

        self::assertSame(
            ['Key' => 'a.txt', 'ContentLength' => 1],
            $storage->put("\0a.txt\0", 'x'),
        );
        self::assertSame('a.txt', $storage->get("a.txt\0")['Key']);
    }

    #[DataProvider('validKeys')]
    public function testAcceptsValidKeys(string $key, string $normalized): void
    {
        $storage = new LocalStorage($this->rootPath);

        self::assertSame($normalized, $storage->put($key, 'x')['Key']);
        self::assertSame('x', $storage->get($normalized)['Body']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validKeys(): array
    {
        return [
            'simples' => ['a.txt', 'a.txt'],
            'subpasta' => ['reports/example.txt', 'reports/example.txt'],
            'barra invertida' => ['reports\\2026\\a.txt', 'reports/2026/a.txt'],
            'espacos nas pontas' => ['  reports/a.txt  ', 'reports/a.txt'],
            'pontos dentro de segmentos' => ['v1.0/..a/arquivo..txt', 'v1.0/..a/arquivo..txt'],
        ];
    }

    #[DataProvider('invalidKeys')]
    public function testRejectsInvalidKeysInAllMethods(string $key): void
    {
        $storage = new LocalStorage($this->rootPath);
        $calls = [
            'put' => static fn() => $storage->put($key, 'x'),
            'get' => static fn() => $storage->get($key),
            'delete' => static fn() => $storage->delete($key),
            'temporaryUrl' => static fn() => $storage->temporaryUrl($key),
        ];

        foreach ($calls as $method => $call) {
            try {
                $call();
                self::fail("{$method} deveria rejeitar a chave.");
            } catch (InvalidArgumentException $exception) {
                self::assertSame('A chave de storage e invalida.', $exception->getMessage(), $method);
            }
        }

        // A validação acontece antes de qualquer acesso ao disco.
        self::assertDirectoryDoesNotExist($this->rootPath);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKeys(): array
    {
        return [
            'vazia' => [''],
            'so espacos' => ['   '],
            'absoluta' => ['/etc/passwd'],
            'absoluta com barra invertida' => ['\\etc\\passwd'],
            'segmento ponto-ponto no inicio' => ['../fora.txt'],
            'segmento ponto-ponto no meio' => ['a/../b.txt'],
            'segmento ponto-ponto no fim' => ['a/..'],
            'ponto-ponto com barra invertida' => ['a\\..\\b.txt'],
            'segmento ponto no inicio' => ['./a.txt'],
            'segmento ponto no meio' => ['a/./b.txt'],
            'segmento vazio' => ['a//b.txt'],
            'termina em barra' => ['a/b/'],
            'byte nulo no meio' => ["a\0b.txt"],
        ];
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
