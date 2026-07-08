# elavora/api-storage-local

Armazenamento local opcional para o framework Elavora.

Registre `LocalStorageExtension` informando o diretorio raiz onde os objetos
serao gravados. O servico registrado implementa
`Elavora\Api\Framework\Contracts\Storage`.

```php
$application->extend(new LocalStorageExtension('/var/app/storage'));
$storage = $application->container()->get(Storage::class);

$storage->put('reports/example.txt', 'conteudo');
```

Este pacote e aditivo. Ele nao substitui `Elavora\Api\Interface\Storage` nem as
classes de storage existentes no modulo legado `elavora/api-api`.
