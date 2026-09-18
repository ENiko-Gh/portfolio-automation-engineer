<?php

/**
 * includes/db.php — VERSIÓN CORREGIDA
 *
 * FIXES vs tu versión original:
 *  1. count() acepta AMBOS: named params (':status'=>'pending') Y posicionales ('?')
 *  2. fetchOne() añadido — para chatbot, CRM, reactions
 *  3. fetchAll() añadido — para chatbot, CRM, reactions
 *  4. tableExists() protegido contra SQL injection en nombre de tabla
 *  5. Todo lo demás igual — sin breaking changes
 */

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../api/config.php';
}

class Database
{
    private $host   = DB_HOST;
    private $dbname = DB_NAME;
    private $user   = DB_USER;
    private $pass   = DB_PASS;
    private $pdo    = null;
    private $error  = null;

    public function __construct()
    {
        $this->connect();
    }

    private function connect()
    {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log("Database Connection Error: " . $this->error);
            throw new Exception("Database connection failed: " . $this->error);
        }
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Query execution failed");
        }
    }

    public function insert($table, $data)
    {
        $keys         = array_keys($data);
        $fields       = implode(', ', $keys);
        $placeholders = ':' . implode(', :', $keys);
        $sql          = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Insert Error: " . $e->getMessage());
            throw new Exception("Insert operation failed");
        }
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        $set    = [];
        $values = [];
        foreach ($data as $key => $value) {
            $set[]    = "{$key} = ?";
            $values[] = $value;
        }
        $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE {$where}";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($values, $whereParams));
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Update Error: " . $e->getMessage());
            throw new Exception("Update operation failed");
        }
    }

    public function delete($table, $where, $params = [])
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Delete Error: " . $e->getMessage());
            throw new Exception("Delete operation failed");
        }
    }

    public function select($table, $where = '1=1', $params = [], $fields = '*')
    {
        $sql = "SELECT {$fields} FROM {$table} WHERE {$where}";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Select Error: " . $e->getMessage());
            throw new Exception("Select operation failed");
        }
    }

    /**
     * FIX CRÍTICO: count() ahora acepta AMBOS formatos
     * Named:      count('appointments','status = :status',[':status'=>'pending'])
     * Positional: count('appointments','status = ?',['pending'])
     * Sin params: count('visitors')
     */
    public function count($table, $where = '1=1', $params = [])
    {
        $sql = "SELECT COUNT(*) as total FROM {$table} WHERE {$where}";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return (int)($result['total'] ?? 0);
        } catch (PDOException $e) {
            error_log("Count Error: " . $e->getMessage() . " | Table: {$table}");
            return 0;
        }
    }

    /**
     * FIX: tableExists() protegido contra SQL injection
     */
    public function tableExists($table)
    {
        try {
            $safe   = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $result = $this->pdo->query("SHOW TABLES LIKE '{$safe}'");
            return $result->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /** NUEVO: obtener una fila con SQL completo */
    public function fetchOne($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("fetchOne Error: " . $e->getMessage());
            return null;
        }
    }

    /** NUEVO: obtener múltiples filas con SQL completo */
    public function fetchAll($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("fetchAll Error: " . $e->getMessage());
            return [];
        }
    }
}
