<?php

namespace DevTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        if(!$args) return $query;

        foreach ($args as $value) {
            preg_match('/\?(.)/', $query, $matches);

            switch ($matches[0]) {
                case '?d':
                    $value = (int)$value;
                    break;
                case '?f':
                    $value = (float)$value;
                    break;
                case '?a':
                    $value = $this->arrayToSqlCondition($value);
                    break;
                case '?#':
                    if (is_array($value)){
                        $value = array_map(function($item) {
                            return "`$item`";
                        }, $value);
                        $value = implode(', ', $value);
                    }elseif (is_string($value)){
                        $value = "`$value`";
                    }
                    break;
                case '? ':
                    $value = "'$value'";
                    break;
            }

            $query = $this->replaceFirstOccurrence($query, trim($matches[0]), $value);
        }

        return $this->handleConditionalBlocks($query, $args);
    }

    private function handleConditionalBlocks($query, $args): string
    {
        preg_match_all('/\{([^}]*)\}/', $query, $blocks, PREG_OFFSET_CAPTURE);

        foreach ($blocks[0] as $index => $block) {
            $blockQuery = $block[0];
            $blockStart = $block[1];
            $blockContent = $blocks[1][$index][0];

            $hasSpecialValue = false;
            foreach ($args as $arg) {
                if ($arg === $this->skip()) {
                    $hasSpecialValue = true;
                    break;
                }
            }

            if ($hasSpecialValue) {
                $query = substr_replace($query, '', $blockStart, strlen($blockQuery));
            } else {
                $query = substr_replace($query, $blockContent, $blockStart, strlen($blockQuery));
            }
        }

        return $query;
    }

    public function arrayToSqlCondition($array)
    {
        $conditions = [];

        $isAssociative = array_keys($array) !== range(0, count($array) - 1);

        foreach ($array as $key => $value) {
            if ($isAssociative) {
                $field = "`$key` = ";
            } else {
                $field = '';
            }

            if (is_null($value)) {
                $conditions[] = $field . 'NULL';
            } elseif (is_bool($value)) {
                $conditions[] = $field . ($value ? '1' : '0');
            } elseif (is_numeric($value)) {
                $conditions[] = $field . addslashes($value);
            }
            else {
                $conditions[] = $field . "'" . addslashes($value) . "'";
            }
        }
        return implode(', ', $conditions);
    }

    function replaceFirstOccurrence($string, $symbol, $value) {
        $position = strpos($string, $symbol);

        if ($position !== false) {
            $before = substr($string, 0, $position);
            $after = substr($string, $position + strlen($symbol));
            return $before . $value . $after;
        }
        return $string;
    }

    public function skip()
    {
        return 'SKIP';
    }
}
