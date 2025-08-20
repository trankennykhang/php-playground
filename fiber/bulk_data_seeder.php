<?php
declare(strict_types=1);

/**
 * Bulk data seeder for MySQL.
 * Run: php seed.php
 *
 * Adjust the configuration below or set environment variables with the same names.
 * For very large imports, consider running on the same host as the DB server and
 * using reasonable batch sizes (e.g., 1,000–10,000).
 */

// ---- Configuration ----
$DB_HOST   = getenv('DB_HOST')   ?: 'localhost';
$DB_NAME   = getenv('DB_NAME')   ?: 'big_test_db';
$DB_USER   = getenv('DB_USER')   ?: 'admin';
$DB_PASS   = getenv('DB_PASS')   ?: 'admin';
$DB_TABLE  = getenv('DB_TABLE')  ?: 'user'; // Table must have columns: Name, Dob, Email, City, Country, Sex

$TOTAL_ROWS = (int)(getenv('TOTAL_ROWS') ?: 30_000_000); // total rows to insert
$BATCH_SIZE = (int)(getenv('BATCH_SIZE') ?: 10_000);     // rows per INSERT
$DISABLE_CHECKS = filter_var(getenv('DISABLE_CHECKS') ?: '1', FILTER_VALIDATE_BOOL); // temporarily disable FK/unique checks for speed

// ---- Safety & runtime ----
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Please run this script via CLI.\n");
    exit(1);
}
if ($BATCH_SIZE < 1 || $BATCH_SIZE > 50_000) {
    fwrite(STDERR, "BATCH_SIZE must be between 1 and 50000.\n");
    exit(1);
}
set_time_limit(0);
ini_set('memory_limit', '-1');

// ---- Optional: try to load Faker ----
$faker = null;
$fakerAvailable = false;
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];
foreach ($autoloadPaths as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists(\Faker\Factory::class)) {
            $faker = \Faker\Factory::create();
            $faker->seed(12345);
            $fakerAvailable = true;
        }
        break;
    }
}

// ---- Fallback lightweight generators ----
$FIRST_NAMES = ['Alex','Sam','Taylor','Jordan','Casey','Riley','Morgan','Jamie','Cameron','Avery','Drew','Quinn','Reese','Rowan','Skyler'];
$LAST_NAMES  = ['Smith','Johnson','Williams','Brown','Jones','Miller','Davis','Garcia','Rodriguez','Wilson','Martinez','Anderson','Taylor','Thomas'];
$CITIES      = ['New York','London','Berlin','Paris','Madrid','Rome','Toronto','Sydney','Tokyo','Seoul','Singapore','Dubai','Sao Paulo','Mexico City'];
$COUNTRIES   = ['United States','United Kingdom','Germany','France','Spain','Italy','Canada','Australia','Japan','South Korea','Singapore','United Arab Emirates','Brazil','Mexico'];

$rand = random_int(...[0, PHP_INT_MAX]); // warm-up RNG

$randName = static function () use ($fakerAvailable, $faker, $FIRST_NAMES, $LAST_NAMES): string {
    if ($fakerAvailable) {
        return $faker->name();
    }
    return $FIRST_NAMES[array_rand($FIRST_NAMES)] . ' ' . $LAST_NAMES[array_rand($LAST_NAMES)];
};

$randCity = static function () use ($fakerAvailable, $faker, $CITIES): string {
    if ($fakerAvailable) {
        return $faker->city();
    }
    return $CITIES[array_rand($CITIES)];
};

$randCountry = static function () use ($fakerAvailable, $faker, $COUNTRIES): string {
    if ($fakerAvailable) {
        return $faker->country();
    }
    return $COUNTRIES[array_rand($COUNTRIES)];
};

$randDob = static function (): string {
    // Between 1950-01-01 and 2010-12-31
    $start = strtotime('1950-01-01');
    $end   = strtotime('2010-12-31');
    $ts    = random_int($start, $end);
    return date('Y-m-d', $ts);
};

$randSex = static function (): string {
    // Keep values simple and compact: 'M' or 'F'
    return (random_int(0, 1) === 1) ? 'M' : 'F';
};

// ---- Connect via PDO ----
$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Throwable $e) {
    fwrite(STDERR, "Connection failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

// ---- Optionally speed up by relaxing checks (re-enable after) ----
$checksDisabled = false;
if ($DISABLE_CHECKS) {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('SET UNIQUE_CHECKS=0');
        $checksDisabled = true;
    } catch (Throwable $e) {
        // Not critical; continue without disabling
    }
}

// ---- Insertion loop ----
$columns = ['Name','Dob','Email','City','Country','Sex'];
$totalBatches = (int)ceil($TOTAL_ROWS / $BATCH_SIZE);
$inserted = 0;
$startTime = microtime(true);

echo "Seeding {$TOTAL_ROWS} rows into {$DB_NAME}.{$DB_TABLE} (batch size: {$BATCH_SIZE})...\n";

for ($batch = 0; $batch < $totalBatches; $batch++) {
    $rowsThisBatch = min($BATCH_SIZE, $TOTAL_ROWS - $inserted);
    if ($rowsThisBatch <= 0) {
        break;
    }

    $placeholders = [];
    $values = [];

    // Generate data
    for ($i = 0; $i < $rowsThisBatch; $i++) {
        // Use global row index for unique email
        $globalIndex = $inserted + $i + 1;
        $name = $randName();
        $dob = $randDob();
        $email = "user{$globalIndex}@example.com";
        $city = $randCity();
        $country = $randCountry();
        $sex = $randSex();

        $placeholders[] = '(?, ?, ?, ?, ?, ?)';
        array_push($values, $name, $dob, $email, $city, $country, $sex);
    }

    // Insert batch inside a transaction
    $pdo->beginTransaction();
    try {
        $sql = "INSERT INTO `{$DB_TABLE}` (`Name`, `Dob`, `Email`, `City`, `Country`, `Sex`) VALUES " . implode(',', $placeholders);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "Batch " . ($batch + 1) . " failed: " . $e->getMessage() . PHP_EOL);
        // Optionally exit or continue; here we exit to keep integrity
        exit(1);
    }

    $inserted += $rowsThisBatch;

    if ($inserted % (100_000) === 0 || $inserted === $TOTAL_ROWS) {
        $elapsed = microtime(true) - $startTime;
        $rate = $elapsed > 0 ? number_format($inserted / $elapsed, 0) : 'n/a';
        echo "{$batch} / {$totalBatches} - Inserted {$inserted}/{$TOTAL_ROWS} rows (≈{$rate} rows/s)\n";
    }
}

// Re-enable checks if we disabled them
if ($checksDisabled) {
    try {
        $pdo->exec('SET UNIQUE_CHECKS=1');
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $e) {
        // Non-fatal; log and continue
        fwrite(STDERR, "Warning: could not re-enable checks: " . $e->getMessage() . PHP_EOL);
    }
}

$elapsed = microtime(true) - $startTime;
$rate = $elapsed > 0 ? number_format($inserted / $elapsed, 0) : 'n/a';
echo "Done. Inserted {$inserted} rows in " . number_format($elapsed, 2) . "s (≈{$rate} rows/s)\n";
