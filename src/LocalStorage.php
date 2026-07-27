<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\StorageLocal;

use Closure;
use DateTimeImmutable;
use Elavora\Api\Framework\Contracts\Storage;
use InvalidArgumentException;
use RuntimeException;

final class LocalStorage implements Storage
{
    private readonly string $rootPath;

    /** @var Closure(string, string): bool */
    private readonly Closure $replaceFile;

    /**
     * @param string $rootPath Diretorio raiz onde os arquivos serao gravados.
     * @param null|callable(string, string): bool $replaceFile Substituicao injetavel para falhas controladas.
     */
    public function __construct(string $rootPath, ?callable $replaceFile = null)
    {
        $rootPath = trim($rootPath);
        if ($rootPath === '') {
            throw new InvalidArgumentException('O diretorio raiz do storage local e obrigatorio.');
        }

        if (!is_dir($rootPath) && !@mkdir($rootPath, 0775, true) && !is_dir($rootPath)) {
            throw new RuntimeException('Nao foi possivel criar o diretorio raiz do storage local.');
        }

        $canonicalRoot = realpath($rootPath);
        if ($canonicalRoot === false || !is_dir($canonicalRoot)) {
            throw new RuntimeException('Nao foi possivel resolver o diretorio raiz do storage local.');
        }

        $this->rootPath = $canonicalRoot;
        $this->replaceFile = $replaceFile === null
            ? static fn (string $temporaryPath, string $destinationPath): bool => @rename(
                $temporaryPath,
                $destinationPath
            )
            : Closure::fromCallable($replaceFile);
    }

    /**
     * Grava um conteudo no storage local.
     *
     * @param string $key Chave relativa do arquivo.
     * @param string $body Conteudo a ser gravado.
     * @param array<string, mixed> $options Opcoes reservadas para compatibilidade com Storage.
     * @return array{Key: string, ContentLength: int}
     */
    public function put(string $key, string $body, array $options = []): array
    {
        $key = $this->normalizedKey($key);
        $path = $this->pathFor($key, true);

        if (is_dir($path)) {
            throw new RuntimeException('A chave do storage local aponta para um diretorio.');
        }

        $temporaryPath = @tempnam(dirname($path), '.elavora-storage-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Nao foi possivel criar o arquivo temporario do storage local.');
        }

        /** @var resource|null $handle */
        $handle = null;

        try {
            $handle = @fopen($temporaryPath, 'wb');
            if ($handle === false) {
                $handle = null;
                throw new RuntimeException('Nao foi possivel abrir o arquivo temporario do storage local.');
            }

            $this->writeAll($handle, $body);

            if (!@fflush($handle)) {
                throw new RuntimeException('Nao foi possivel finalizar o arquivo temporario do storage local.');
            }

            $closed = @fclose($handle);
            $handle = null;
            if (!$closed) {
                throw new RuntimeException('Nao foi possivel fechar o arquivo temporario do storage local.');
            }

            $path = $this->pathFor($key, false);
            if (!($this->replaceFile)($temporaryPath, $path)) {
                throw new RuntimeException('Nao foi possivel substituir o arquivo no storage local.');
            }

            $temporaryPath = null;
        } finally {
            if (is_resource($handle)) {
                @fclose($handle);
            }

            if ($temporaryPath !== null && file_exists($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        return ['Key' => $key, 'ContentLength' => strlen($body)];
    }

    /**
     * Le um arquivo do storage local.
     *
     * @param string $key Chave relativa do arquivo.
     * @param array<string, mixed> $options Opcoes reservadas para compatibilidade com Storage.
     * @return array{Key: string, Body: string, ContentLength: int}
     */
    public function get(string $key, array $options = []): array
    {
        $key = $this->normalizedKey($key);
        $path = $this->pathFor($key, false);

        if (!is_file($path)) {
            throw new RuntimeException('Arquivo nao encontrado no storage local.');
        }

        $body = @file_get_contents($path);
        if ($body === false) {
            throw new RuntimeException('Nao foi possivel ler o arquivo do storage local.');
        }

        return ['Key' => $key, 'Body' => $body, 'ContentLength' => strlen($body)];
    }

    /**
     * Remove um arquivo do storage local.
     *
     * @param string $key Chave relativa do arquivo.
     * @param array<string, mixed> $options Opcoes reservadas para compatibilidade com Storage.
     * @return array{Key: string, Deleted: bool}
     */
    public function delete(string $key, array $options = []): array
    {
        $key = $this->normalizedKey($key);
        $path = $this->pathFor($key, false);

        return ['Key' => $key, 'Deleted' => !is_file($path) || @unlink($path)];
    }

    /**
     * Retorna uma URL file:// para o arquivo local.
     *
     * @param string $key Chave relativa do arquivo.
     * @param DateTimeImmutable|null $expiresAt Ignorado pelo storage local.
     * @param array<string, mixed> $options Opcoes reservadas para compatibilidade com Storage.
     */
    public function temporaryUrl(
        string $key,
        ?DateTimeImmutable $expiresAt = null,
        array $options = []
    ): string {
        return 'file://' . $this->pathFor($this->normalizedKey($key), false);
    }

    /**
     * @param resource $handle
     */
    private function writeAll($handle, string $body): void
    {
        $length = strlen($body);
        $offset = 0;

        while ($offset < $length) {
            $writtenBytes = @fwrite($handle, substr($body, $offset));
            if ($writtenBytes === false || $writtenBytes === 0) {
                throw new RuntimeException('Nao foi possivel gravar o arquivo temporario do storage local.');
            }

            $offset += $writtenBytes;
        }
    }

    private function pathFor(string $key, bool $createParents): string
    {
        $segments = explode('/', $key);
        $fileName = array_pop($segments);

        $directory = $this->rootPath;

        foreach ($segments as $segment) {
            $candidate = $this->joinPath($directory, $segment);
            $this->assertNotSymbolicLink($candidate);

            if (!is_dir($candidate) && file_exists($candidate)) {
                throw new InvalidArgumentException('A chave de storage possui um diretorio invalido.');
            }

            if (!is_dir($candidate) && $createParents) {
                if (!@mkdir($candidate, 0775) && !is_dir($candidate)) {
                    throw new RuntimeException('Nao foi possivel criar o diretorio do storage local.');
                }

                $this->assertNotSymbolicLink($candidate);
            }

            if (is_dir($candidate)) {
                $canonicalDirectory = realpath($candidate);
                if ($canonicalDirectory === false) {
                    throw new RuntimeException('Nao foi possivel resolver o caminho do storage local.');
                }

                $this->assertInsideRoot($canonicalDirectory);
                $directory = $canonicalDirectory;
            } else {
                $directory = $candidate;
            }
        }

        $path = $this->joinPath($directory, $fileName);
        $this->assertNotSymbolicLink($path);

        if (file_exists($path)) {
            $canonicalPath = realpath($path);
            if ($canonicalPath === false) {
                throw new RuntimeException('Nao foi possivel resolver o caminho do storage local.');
            }

            $this->assertInsideRoot($canonicalPath);

            return $canonicalPath;
        }

        return $path;
    }

    private function assertNotSymbolicLink(string $path): void
    {
        clearstatcache(true, $path);

        if (is_link($path)) {
            throw new InvalidArgumentException('A chave de storage nao pode atravessar links simbolicos.');
        }
    }

    private function assertInsideRoot(string $path): void
    {
        $root = rtrim($this->rootPath, '\\/');
        $root = $root === '' ? DIRECTORY_SEPARATOR : $root;
        $prefix = $root === DIRECTORY_SEPARATOR ? $root : $root . DIRECTORY_SEPARATOR;

        $comparablePath = DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
        $comparableRoot = DIRECTORY_SEPARATOR === '\\' ? strtolower($root) : $root;
        $comparablePrefix = DIRECTORY_SEPARATOR === '\\' ? strtolower($prefix) : $prefix;

        if ($comparablePath !== $comparableRoot && !str_starts_with($comparablePath, $comparablePrefix)) {
            throw new InvalidArgumentException('A chave de storage aponta para fora do diretorio raiz.');
        }
    }

    private function joinPath(string $directory, string $segment): string
    {
        return rtrim($directory, '\\/') . DIRECTORY_SEPARATOR . $segment;
    }

    private function normalizedKey(string $key): string
    {
        $normalizedKey = str_replace('\\', '/', trim($key));

        if ($normalizedKey === '' || str_starts_with($normalizedKey, '/')) {
            throw new InvalidArgumentException('A chave de storage e invalida.');
        }

        foreach (explode('/', $normalizedKey) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, "\0")) {
                throw new InvalidArgumentException('A chave de storage e invalida.');
            }
        }

        return $normalizedKey;
    }
}
