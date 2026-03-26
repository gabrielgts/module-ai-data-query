<?php

namespace Gtstudio\AiDataQuery\Model;

use Gtstudio\AiDataQuery\Api\EntityRegistryInterface;
use Gtstudio\AiDataQuery\Api\Data\EntityInterface;
use Gtstudio\AiDataQuery\Model\Data\Entity;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotDeleteException;

class EntityRegistry implements EntityRegistryInterface
{
    /** @var ResourceConnection */
    private ResourceConnection $resourceConnection;

    /** @var array */
    private array $entities = [];

    /** @var bool */
    private bool $loaded = false;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Get entity by code.
     *
     * @param string $code
     * @return EntityInterface|null
     */
    public function get(string $code): ?EntityInterface
    {
        $this->loadAll();

        return $this->entities[$code] ?? null;
    }

    /**
     * Get all registered entities.
     *
     * @return array
     */
    public function getAll(): array
    {
        $this->loadAll();

        return $this->entities;
    }

    /**
     * Check if entity is queryable.
     *
     * @param string $code
     * @return bool
     */
    public function isQueryable(string $code): bool
    {
        $entity = $this->get($code);

        return $entity !== null && $entity->isActive();
    }

    /**
     * Load all active entities from the database.
     *
     * @return void
     */
    private function loadAll(): void
    {
        if ($this->loaded) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('gtstudio_ai_entities');

        $select = $connection->select()->from($table)->where('is_active = ?', 1);

        $results = $connection->fetchAll($select);

        foreach ($results as $row) {
            $entity = new Entity($row);
            $this->entities[$entity->getCode()] = $entity;
        }

        $this->loaded = true;
    }
}
