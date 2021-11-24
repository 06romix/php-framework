<?php
declare(strict_types=1);

namespace Dev256\Framework;

class DbAdapter
{
    private $pdo;

    public function __construct(private EnvConfig $config) {}

    public function getPdo(): \PDO
    {
        if (null === $this->pdo) {
            $dbConfig = $this->config->getDbConfig();
            $dsn = "mysql:dbname={$dbConfig['dbname']};host={$dbConfig['host']};charset=utf8;";
            $this->pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password']);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,  \PDO::ERRMODE_EXCEPTION);
        }

        return $this->pdo;
    }

    public function getAll(string $tableName): array
    {
        $stmt = $this->getPdo()->query("SELECT * FROM `$tableName`");
        return $stmt->fetchAll();
    }

    public function save(string $tableName, string $idFieldName, array $rowData): int
    {
        if (! $rowData[$idFieldName]) {
            return $this->create($tableName, $idFieldName, $rowData);
        }

        return $this->update($tableName, $idFieldName, $rowData);
    }

    public function create(string $tableName, string $idFieldName, array $rowData): int
    {
        unset($rowData[$idFieldName]);
        $columns = array_keys($rowData);
        $valuesMarkers = array_map(static function ($column) {
            return ":$column";
        }, $columns);

        $columnStr = implode(', ', array_map(static function ($column) {
            return "`$column`";
        }, $columns));

        $valuesMarkersStr = implode(', ', $valuesMarkers);

        $stmt = $this->getPdo()->prepare("INSERT INTO `$tableName` ($columnStr) VALUES ($valuesMarkersStr)");

        $stmt->execute(array_combine($valuesMarkers, $rowData));
        return (int) $this->getPdo()->lastInsertId();
    }

    private function update(string $tableName, string $idFieldName, array $rowData): int
    {
        return $rowData[$idFieldName];
    }

    /**
     * @param string $tableName
     * @param string $idFieldName
     * @param int    $id
     * @return string[]
     */
    public function getOne(string $tableName, string $idFieldName, int $id): array
    {
        $stmt = $this->getPdo()->prepare("SELECT * FROM `$tableName` WHERE `$idFieldName` = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
}
