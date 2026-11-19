<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;

/*
 * The anime list: filters, categories, sorting and CURSOR pagination.
 *
 * Why cursors instead of ?page=N:
 *   ?page=40 makes the database read and throw away the first 780 rows every time.
 *   A cursor says "start right after THIS row", so the database jumps straight there
 *   using the sort value + id of the last row on the current page.
 *
 * Query parameters (all optional):
 *   showtype=TV  agerating=PG  category[]=Action&category[]=Comedy  sort=title_asc
 *   limit=5|10|20            (default 20)
 *   after=<cursor>           next page   (cursor from the X-Next-Cursor header)
 *   before=<cursor>          previous page (cursor from the X-Prev-Cursor header)
 *
 * list() returns ['records' => [...], 'next' => ?string, 'prev' => ?string]
 * and throws InvalidArgumentException (-> HTTP 400) for anything not allowed.
 */
class AnimeListService
{
    public function __construct(private PDO $pdo, private array $config)
    {
    }

    public function list(array $query): array
    {
        $limit = $this->limit($query);
        [$where, $values] = $this->filters($query);

        $sortKey = $this->string($query, 'sort') ?: 'default';
        if (!isset($this->config['sort'][$sortKey])) {
            throw new InvalidArgumentException(
                'Invalid sort. Allowed: ' . implode(', ', array_keys($this->config['sort']))
            );
        }
        $sort = $this->config['sort'][$sortKey];
        $expr = $sort['expr'];
        $dir = $sort['dir'];

        $after = $this->string($query, 'after');
        $before = $this->string($query, 'before');
        if ($after !== '' && $before !== '') {
            throw new InvalidArgumentException('Use either after or before, not both.');
        }

        // Going backwards (Previous): flip the comparison and the order, then reverse the rows.
        $backwards = $before !== '';
        $orderDir = $backwards ? $this->flip($dir) : $dir;

        if ($after !== '' || $before !== '') {
            $cursor = $this->decodeCursor($after !== '' ? $after : $before, $sortKey);
            $op = ($orderDir === 'ASC') ? '>' : '<';
            // Row comparison: (sort value, id) comes strictly after the cursor row
            $where[] = "($expr, id) $op (CAST(:cursor_value AS {$sort['cast']}), :cursor_id)";
            $values[':cursor_value'] = $cursor['v'];
            $values[':cursor_id'] = $cursor['id'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Ask for one extra row: if it comes back, there is another page in that direction.
        $stmt = $this->pdo->prepare(
            "SELECT *, $expr AS cursor_value
             FROM public.anime
             $whereSql
             ORDER BY $expr $orderDir, id $orderDir
             LIMIT :limit"
        );
        foreach ($values as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        if ($backwards) {
            $rows = array_reverse($rows);
        }

        if ($backwards) {
            $hasNext = true;          // we came from the next page
            $hasPrev = $hasMore;
        } else {
            $hasNext = $hasMore;
            $hasPrev = $after !== '';  // the first page has nothing before it
        }

        $first = $rows[0] ?? null;
        $last = $rows === [] ? null : $rows[count($rows) - 1];

        $next = ($hasNext && $last) ? $this->encodeCursor($sortKey, $last) : null;
        $prev = ($hasPrev && $first) ? $this->encodeCursor($sortKey, $first) : null;

        // cursor_value was only needed to build the cursors
        $records = array_map(static function (array $row): array {
            unset($row['cursor_value']);
            return $row;
        }, $rows);

        return ['records' => $records, 'next' => $next, 'prev' => $prev];
    }

    // ?limit=5|10|20
    private function limit(array $query): int
    {
        $raw = $this->string($query, 'limit');
        if ($raw === '') {
            return (int) $this->config['limit']['default'];
        }
        $limit = filter_var($raw, FILTER_VALIDATE_INT);
        if ($limit === false || !in_array($limit, $this->config['limit']['options'], true)) {
            throw new InvalidArgumentException(
                'Invalid limit. Allowed: ' . implode(', ', $this->config['limit']['options'])
            );
        }
        return $limit;
    }

    // showtype, agerating, category[]  ->  [where clauses, bound values]
    private function filters(array $query): array
    {
        $where = [];
        $values = [];

        foreach (['showtype', 'agerating'] as $filter) {
            $value = $this->string($query, $filter);
            if ($value === '') {
                continue;
            }
            if (!array_key_exists($value, $this->config[$filter]['options'])) {
                throw new InvalidArgumentException(
                    "Invalid $filter. Allowed: " . implode(', ', array_keys($this->config[$filter]['options']))
                );
            }
            $where[] = $this->config[$filter]['column'] . " = :$filter";
            $values[":$filter"] = $value;
        }

        // category[]=Action&category[]=Comedy  (also accepts a single ?category=Action)
        $categories = $query['category'] ?? [];
        if (is_string($categories)) {
            $categories = $categories === '' ? [] : [$categories];
        }
        if (!is_array($categories)) {
            throw new InvalidArgumentException('Invalid category.');
        }
        $categories = array_values(array_unique($categories));

        $placeholders = [];
        foreach ($categories as $i => $category) {
            if (!is_string($category) || !in_array($category, $this->config['category']['options'], true)) {
                throw new InvalidArgumentException(
                    'Invalid category. Allowed: ' . implode(', ', $this->config['category']['options'])
                );
            }
            $placeholders[] = ":category$i";
            $values[":category$i"] = $category;
        }
        if ($placeholders) {
            // @> means "contains all of": the anime must have every ticked category
            $where[] = $this->config['category']['column'] . ' @> ARRAY[' . implode(', ', $placeholders) . ']::text[]';
        }

        return [$where, $values];
    }

    // A cursor is base64url( {"s": sort, "v": sort value, "id": row id} )
    public function encodeCursor(string $sortKey, array $row): string
    {
        $json = json_encode(['s' => $sortKey, 'v' => (string) $row['cursor_value'], 'id' => (int) $row['id']]);
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    public function decodeCursor(string $cursor, string $sortKey): array
    {
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true);

        if (
            !is_array($data)
            || ($data['s'] ?? null) !== $sortKey
            || !is_string($data['v'] ?? null)
            || !is_int($data['id'] ?? null)
        ) {
            // Wrong format, edited by hand, or made for a different sort order
            throw new InvalidArgumentException('Invalid or expired cursor. Go back to the first page.');
        }
        return $data;
    }

    private function flip(string $dir): string
    {
        return $dir === 'ASC' ? 'DESC' : 'ASC';
    }

    private function string(array $query, string $key): string
    {
        $value = $query[$key] ?? '';
        if (!is_string($value)) {
            throw new InvalidArgumentException("Invalid $key.");
        }
        return trim($value);
    }
}
