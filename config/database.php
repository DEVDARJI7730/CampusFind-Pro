<?php
/**
 * CampusFind Pro - Database Class
 * Secure MongoDB-based Database wrapper using the Singleton design pattern.
 */

class Database {
    private static ?Database $instance = null;
    private ?MongoDB\Driver\Manager $manager = null;
    private string $dbName;

    // Private constructor prevents direct instantiation
    private function __construct() {
        try {
            $this->manager = new MongoDB\Driver\Manager(MONGODB_URI);
            $this->dbName = MONGODB_DB;
            
            // Ping the database to verify connectivity
            $command = new MongoDB\Driver\Command(['ping' => 1]);
            $this->manager->executeCommand($this->dbName, $command);
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("
                <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; text-align: center; padding: 50px 20px; background: #fdfdfd; color: #333;'>
                    <div style='max-width: 500px; margin: 0 auto; padding: 40px; border-radius: 16px; box-shadow: 0 4px 30px rgba(0, 0, 0, 0.05); border: 1px solid #eaeaea; background: #fff;'>
                        <h2 style='color: #ff3b30; font-weight: 600; margin-top: 0;'>System Maintenance</h2>
                        <p style='color: #666; font-size: 15px; line-height: 1.6;'>We are experiencing a temporary MongoDB connection issue. Please ensure your MongoDB server or Atlas cluster is running and connection URI is valid.</p>
                        <hr style='border: 0; border-top: 1px solid #eaeaea; margin: 25px 0;'>
                        <button onclick='window.location.reload()' style='background: #007aff; color: #fff; border: 0; padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer; transition: 0.2s;'>Retry Connection</button>
                    </div>
                </div>
            ");
        }
    }

    /**
     * Get Database class instance
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * Get active MongoDB connection manager
     */
    public function getManager(): MongoDB\Driver\Manager {
        return $this->manager;
    }

    /**
     * Get configured database name
     */
    public function getDbName(): string {
        return $this->dbName;
    }

    /**
     * Recursively normalizes BSON array serialization (like converting ['$oid' => 'xxx'] to string 'xxx')
     */
    private function normalizeBSON(array $array): array {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (count($value) === 1 && isset($value['$oid'])) {
                    $array[$key] = $value['$oid'];
                } else {
                    $array[$key] = $this->normalizeBSON($value);
                }
            }
        }
        return $array;
    }

    /**
     * Query documents from a collection
     */
    public function find(string $collection, array $filter = [], array $options = []): array {
        $query = new MongoDB\Driver\Query($filter, $options);
        $namespace = $this->dbName . '.' . $collection;
        $cursor = $this->manager->executeQuery($namespace, $query);
        $results = [];
        foreach ($cursor as $document) {
            $results[] = $this->normalizeBSON(json_decode(json_encode($document), true));
        }
        return $results;
    }

    /**
     * Fetch a single document matching the filter criteria
     */
    public function findOne(string $collection, array $filter = []): ?array {
        $results = $this->find($collection, $filter, ['limit' => 1]);
        return $results[0] ?? null;
    }

    /**
     * Inserts a document into a collection and returns the generated document ID
     */
    public function insert(string $collection, array $document): string {
        if (empty($document['_id'])) {
            $document['_id'] = new MongoDB\BSON\ObjectId();
        }
        
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->insert($document);
        $namespace = $this->dbName . '.' . $collection;
        $this->manager->executeBulkWrite($namespace, $bulk);
        
        return (string)$document['_id'];
    }

    /**
     * Updates documents matching the filter criteria
     */
    public function update(string $collection, array $filter, array $updateData, bool $multi = false): int {
        $bulk = new MongoDB\Driver\BulkWrite();
        
        // Auto-wrap under $set operator if keys are not already update operators
        $firstKey = key($updateData);
        if ($firstKey === null || substr($firstKey, 0, 1) !== '$') {
            $updateData = ['$set' => $updateData];
        }

        $bulk->update($filter, $updateData, ['multi' => $multi, 'upsert' => false]);
        $namespace = $this->dbName . '.' . $collection;
        $result = $this->manager->executeBulkWrite($namespace, $bulk);
        return $result->getModifiedCount() ?? $result->getMatchedCount() ?? 0;
    }

    /**
     * Deletes documents matching the filter criteria
     */
    public function delete(string $collection, array $filter, bool $limit = false): int {
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->delete($filter, ['limit' => $limit ? 1 : 0]);
        $namespace = $this->dbName . '.' . $collection;
        $result = $this->manager->executeBulkWrite($namespace, $bulk);
        return $result->getDeletedCount() ?? 0;
    }

    /**
     * Counts documents matching the filter criteria
     */
    public function count(string $collection, array $filter = []): int {
        $command = new MongoDB\Driver\Command([
            'count' => $collection,
            'query' => empty($filter) ? (object)[] : $filter
        ]);
        $cursor = $this->manager->executeCommand($this->dbName, $command);
        $result = $cursor->toArray()[0] ?? null;
        return $result ? (int)$result->n : 0;
    }

    /**
     * Runs custom aggregation pipelines
     */
    public function aggregate(string $collection, array $pipeline): array {
        $command = new MongoDB\Driver\Command([
            'aggregate' => $collection,
            'pipeline' => $pipeline,
            'cursor' => new stdClass()
        ]);
        $cursor = $this->manager->executeCommand($this->dbName, $command);
        $results = [];
        foreach ($cursor as $document) {
            $results[] = $this->normalizeBSON(json_decode(json_encode($document), true));
        }
        return $results;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Helper function to safely convert a string ID to a MongoDB BSON ObjectId.
 * If the input is already an ObjectId or not a 24-character hex string, returns it as-is.
 */
function toObjectId($id) {
    if (empty($id)) {
        return null;
    }
    if ($id instanceof MongoDB\BSON\ObjectId) {
        return $id;
    }
    if (is_string($id) && strlen($id) === 24 && ctype_xdigit($id)) {
        try {
            return new MongoDB\BSON\ObjectId($id);
        } catch (Exception $e) {
            return $id;
        }
    }
    return $id;
}
