<?php
declare(strict_types=1);

/**
 * Mesin CRUD generik untuk Master Data.
 * Semua nilai dari pengguna dikirim lewat prepared statement; nama tabel/kolom
 * hanya berasal dari includes/masterdata_entities.php.
 */

function crud_entities(): array
{
    static $entities = null;
    if ($entities === null) {
        $entities = require APP_ROOT . '/includes/masterdata_entities.php';
    }
    return $entities;
}

/**
 * Pilihan untuk field bertipe select (statis) atau ref (dari tabel lain).
 * Hasil: [nilai => teks tampil]
 */
function crud_field_options(array $field, string $mode, mixed $current = null): array
{
    if (($field['type'] ?? '') === 'select') {
        return $field['options'];
    }

    $r      = $field['ref'];
    $where  = [];
    $params = [];

    if (!empty($r['where'])) {
        $where[] = '(' . $r['where'] . ')';
    }
    if ($mode === 'create' && !empty($r['where_create'])) {
        $where[] = '(' . $r['where_create'] . ')';
    }
    $cond = $where ? implode(' AND ', $where) : '1 = 1';

    $sql = "SELECT {$r['id']} AS v, {$r['label']} AS l FROM {$r['table']}";
    if ($current !== null && $current !== '') {
        $sql .= " WHERE ($cond) OR {$r['id']} = :cur";
        $params[':cur'] = $current;
    } else {
        $sql .= " WHERE $cond";
    }
    $sql .= ' ORDER BY ' . ($r['order'] ?? $r['label']);

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(string) $row['v']] = (string) $row['l'];
    }
    return $out;
}

/**
 * Kolom SELECT: semua kolom tabel + label untuk field ref.
 */
function crud_select_columns(array $entity): string
{
    $cols = ['t.*'];
    foreach ($entity['fields'] as $name => $f) {
        if (($f['type'] ?? '') === 'ref') {
            $r      = $f['ref'];
            $cols[] = "(SELECT {$r['label']} FROM {$r['table']} WHERE {$r['id']} = t.{$name} LIMIT 1) AS `{$name}__label`";
        }
    }
    return implode(', ', $cols);
}

function crud_find(array $entity, string $id): ?array
{
    $sql  = 'SELECT ' . crud_select_columns($entity)
          . " FROM {$entity['table']} t WHERE t.{$entity['pk']} = :id LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Daftar data dengan pencarian dan paginasi. Hasil: [baris, total].
 */
function crud_list(array $entity, string $q, int $page, int $perPage): array
{
    $params = [];
    $where  = '';

    if ($q !== '') {
        $like  = '%' . addcslashes($q, '%_\\') . '%';
        $conds = [];
        $i     = 0;
        foreach ($entity['fields'] as $name => $f) {
            if (!empty($f['search'])) {
                $key         = ':q' . $i++;
                $conds[]     = "t.{$name} LIKE {$key}";
                $params[$key] = $like;
            }
        }
        foreach ($entity['search_exprs'] ?? [] as $expr) {
            $key         = ':q' . $i++;
            $conds[]     = "{$expr} LIKE {$key}";
            $params[$key] = $like;
        }
        if ($conds) {
            $where = ' WHERE (' . implode(' OR ', $conds) . ')';
        }
    }

    $count = db()->prepare("SELECT COUNT(*) FROM {$entity['table']} t{$where}");
    foreach ($params as $k => $v) {
        $count->bindValue($k, $v);
    }
    $count->execute();
    $total = (int) $count->fetchColumn();

    $sql = 'SELECT ' . crud_select_columns($entity)
         . " FROM {$entity['table']} t{$where} ORDER BY {$entity['order']} LIMIT :lim OFFSET :off";
    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();

    return [$stmt->fetchAll(), $total];
}

/**
 * Validasi input form. Hasil: [data bersih, daftar error].
 */
function crud_validate(array $entity, array $input, string $mode, ?array $existing): array
{
    $data   = [];
    $errors = [];

    foreach ($entity['fields'] as $name => $f) {
        if (!empty($f['readonly'])) {
            continue;
        }
        if ($mode === 'update' && !empty($f['create_only'])) {
            continue;
        }

        $label = $f['label'];
        $raw   = $input[$name] ?? '';
        $raw   = is_string($raw) ? trim($raw) : '';

        if ($raw === '') {
            if (!empty($f['required'])) {
                $errors[] = $label . ' wajib diisi.';
            } else {
                $data[$name] = null;
            }
            continue;
        }

        switch ($f['type']) {
            case 'text':
            case 'textarea':
                if (!empty($f['upper'])) {
                    $raw = mb_strtoupper($raw);
                }
                if (!empty($f['lower'])) {
                    $raw = mb_strtolower($raw);
                }
                if (mb_strlen($raw) > ($f['max'] ?? 255)) {
                    $errors[] = $label . ' maksimal ' . ($f['max'] ?? 255) . ' karakter.';
                    break;
                }
                if (!empty($f['pattern']) && !preg_match($f['pattern'], $raw)) {
                    $errors[] = $f['pattern_msg'] ?? ($label . ' tidak valid.');
                    break;
                }
                $data[$name] = $raw;
                break;

            case 'int':
                if (!preg_match('/^\d{1,9}$/', $raw)) {
                    $errors[] = $label . ' harus berupa bilangan bulat.';
                    break;
                }
                $n = (int) $raw;
                if (isset($f['min']) && $n < $f['min']) {
                    $errors[] = $label . ' minimal ' . $f['min'] . '.';
                    break;
                }
                if (isset($f['max']) && $n > $f['max']) {
                    $errors[] = $label . ' maksimal ' . $f['max'] . '.';
                    break;
                }
                $data[$name] = $n;
                break;

            case 'decimal':
                if (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', $raw)) {
                    $errors[] = $label . ' harus berupa angka tanpa titik ribuan (contoh: 75000 atau 75000.50).';
                    break;
                }
                if (isset($f['min']) && (float) $raw < $f['min']) {
                    $errors[] = $label . ' minimal ' . $f['min'] . '.';
                    break;
                }
                $data[$name] = $raw;
                break;

            case 'select':
            case 'ref':
                $options = crud_field_options($f, $mode, $existing[$name] ?? null);
                if (!array_key_exists($raw, $options)) {
                    $errors[] = $label . ' tidak valid.';
                    break;
                }
                $data[$name] = $raw;
                break;

            case 'time':
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $raw)) {
                    $errors[] = $label . ' harus berformat JJ:MM (contoh 08:00).';
                    break;
                }
                $data[$name] = $raw . ':00';
                break;

            default:
                $errors[] = $label . ': tipe field tidak dikenal.';
        }
    }

    if (!$errors && isset($entity['validate'])) {
        $errors = array_merge($errors, ($entity['validate'])($data, $existing));
    }

    return [$data, $errors];
}

function crud_insert(array $entity, array $data): string
{
    $cols = array_keys($data);
    $sql  = 'INSERT INTO ' . $entity['table']
          . ' (`' . implode('`, `', $cols) . '`) VALUES ('
          . implode(', ', array_map(fn($c) => ':' . $c, $cols)) . ')';

    $stmt = db()->prepare($sql);
    foreach ($data as $col => $value) {
        $stmt->bindValue(':' . $col, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    }
    $stmt->execute();

    return !empty($entity['pk_manual']) ? (string) $data[$entity['pk']] : (string) db()->lastInsertId();
}

function crud_update(array $entity, string $id, array $data): void
{
    $sets = [];
    foreach (array_keys($data) as $col) {
        $sets[] = "`{$col}` = :{$col}";
    }
    $sql = 'UPDATE ' . $entity['table'] . ' SET ' . implode(', ', $sets)
         . " WHERE `{$entity['pk']}` = :__id";

    $stmt = db()->prepare($sql);
    foreach ($data as $col => $value) {
        $stmt->bindValue(':' . $col, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    }
    $stmt->bindValue(':__id', $id);
    $stmt->execute();
}

/**
 * Ubah status aktif/nonaktif (pengganti hapus).
 */
function crud_set_active(array $entity, string $id, bool $active): void
{
    $sql  = "UPDATE {$entity['table']} SET `{$entity['active']}` = :v WHERE `{$entity['pk']}` = :id";
    $stmt = db()->prepare($sql);
    $stmt->execute([':v' => $active ? 1 : 0, ':id' => $id]);
}

/**
 * Isi sel tabel (sudah di-escape, aman dicetak langsung).
 */
function crud_cell(array $f, array $row, string $name): string
{
    $v = $row[$name] ?? null;
    if ($v === null || $v === '') {
        return '<span class="muted">&mdash;</span>';
    }

    switch ($f['type']) {
        case 'ref':
            return e($row[$name . '__label'] ?? $v);
        case 'select':
            return e($f['options'][$v] ?? $v);
        case 'time':
            return e(substr((string) $v, 0, 5));
        case 'datetime':
            return e(date('d-m-Y H:i', (int) strtotime((string) $v)));
        case 'decimal':
            if (($f['format'] ?? '') === 'rupiah') {
                return 'Rp ' . e(number_format((float) $v, 0, ',', '.'));
            }
            return e($v);
        case 'textarea':
            return e(mb_strimwidth((string) $v, 0, 70, '...'));
        default:
            return e($v);
    }
}

/**
 * Elemen input untuk satu field di form (sudah di-escape).
 */
function crud_input_html(string $name, array $f, mixed $value, string $mode, ?array $existing): string
{
    $value = $value === null ? '' : (string) $value;
    $id    = 'f_' . $name;
    $req   = !empty($f['required']) ? ' required' : '';

    // Field yang tidak boleh diubah setelah dibuat: tampilkan teks saja
    if ($mode === 'update' && !empty($f['create_only'])) {
        $teks = $f['type'] === 'ref'
            ? (string) ($existing[$name . '__label'] ?? $value)
            : $value;
        return '<input type="text" id="' . e($id) . '" value="' . e($teks) . '" disabled>';
    }

    switch ($f['type']) {
        case 'textarea':
            return '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="3" maxlength="' . (int) ($f['max'] ?? 1000) . '"'
                 . $req . '>' . e($value) . '</textarea>';

        case 'select':
        case 'ref':
            $options = crud_field_options($f, $mode, $existing[$name] ?? null);
            $html    = '<select id="' . e($id) . '" name="' . e($name) . '"' . $req . '>';
            $html   .= '<option value="">&mdash; pilih &mdash;</option>';
            foreach ($options as $v => $teks) {
                $sel   = ((string) $v === $value) ? ' selected' : '';
                $html .= '<option value="' . e((string) $v) . '"' . $sel . '>' . e($teks) . '</option>';
            }
            $html .= '</select>';
            if (!$options) {
                $html .= '<div class="hint warn">Belum ada pilihan yang tersedia.</div>';
            }
            return $html;

        case 'time':
            $v = $value !== '' ? substr($value, 0, 5) : '';
            return '<input type="text" id="' . e($id) . '" name="' . e($name) . '" value="' . e($v)
                 . '" placeholder="08:00" maxlength="5" pattern="([01]\d|2[0-3]):[0-5]\d"' . $req . '>';

        case 'int':
        case 'decimal':
            return '<input type="text" inputmode="decimal" id="' . e($id) . '" name="' . e($name)
                 . '" value="' . e($value) . '" maxlength="16"' . $req . '>';

        default:
            return '<input type="text" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value)
                 . '" maxlength="' . (int) ($f['max'] ?? 255) . '"' . $req . '>';
    }
}

/**
 * Tautan halaman (paginasi). $file = alamat tujuan, $params = parameter tetap.
 */
function crud_pagination_html(string $file, array $params, int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }

    $link = function (int $p, string $teks, bool $aktif = false) use ($file, $params): string {
        $href = e($file . '?' . http_build_query($params + ['page' => $p]));
        return '<a class="page-link' . ($aktif ? ' active' : '') . '" href="' . $href . '">' . e($teks) . '</a>';
    };

    $html = '<div class="pagination">';
    if ($page > 1) {
        $html .= $link($page - 1, '‹ Sebelumnya');
    }
    for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++) {
        $html .= $link($p, (string) $p, $p === $page);
    }
    if ($page < $pages) {
        $html .= $link($page + 1, 'Berikutnya ›');
    }
    return $html . '</div>';
}

/**
 * Navigasi kecil di dalam modul Master Data.
 */
function masterdata_subnav(string $aktif): string
{
    $item = function (string $kunci, string $teks, string $file) use ($aktif): string {
        return '<a class="subnav-item' . ($aktif === $kunci ? ' active' : '') . '" href="'
             . e(url('modules/masterdata/' . $file)) . '">' . e($teks) . '</a>';
    };
    return '<div class="subnav">' . $item('data', '🗂️ Kelola Data', 'index.php')
         . $item('audit', '🧾 Audit Trail', 'audit.php') . '</div>';
}