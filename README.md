WIP

example usage:
```
/** @return array<int, ProductEntity> ID => product */
public function findProductsByIds(array $productIds): array
{
    return $this->entityAdapter->createAll(
        ProductEntity::class,
        $this->connection->executeQuery('SELECT * FROM product WHERE id IN (?)', [$productIds], [ArrayParameterType::INTEGER]),
        [ProductEntity::class, 'getId'],
    );
}
```
