<?php

declare(strict_types=1);

namespace Elavora\Api\Extension\StorageLocal;

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\Extension;
use Elavora\Api\Framework\Contracts\Storage;

final class LocalStorageExtension implements Extension
{
    /**
     * @param string $rootPath Diretorio raiz do storage local.
     */
    public function __construct(private readonly string $rootPath)
    {
    }

    /**
     * Registra o storage local como implementacao de Storage.
     */
    public function register(Application $application): void
    {
        $application->container()->bind(Storage::class, new LocalStorage($this->rootPath));
    }
}
