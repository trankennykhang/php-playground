<?php
declare(strict_types=1);

// ---- Configuration ----
$DB_HOST   = getenv('DB_HOST')   ?: '127.0.0.1';
$DB_NAME   = getenv('DB_NAME')   ?: 'big_test_db';
$DB_USER   = getenv('DB_USER')   ?: 'admin';
$DB_PASS   = getenv('DB_PASS')   ?: 'admin';
$DB_TABLE  = getenv('DB_TABLE')  ?: 'user2';

$TOTAL_ROWS = (int)(getenv('TOTAL_ROWS') ?: 500_000);
$BATCH_SIZE = (int)(getenv('BATCH_SIZE') ?: 20_000);
$DISABLE_CHECKS = filter_var(getenv('DISABLE_CHECKS') ?: '1', FILTER_VALIDATE_BOOL);

$NUM_PROCESSES = (int)(getenv('NUM_PROCESSES') ?: 4); // Number of forks

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Please run this script via CLI.\n");
    exit(1);
}
if ($BATCH_SIZE < 1 || $BATCH_SIZE > 50_000) {
    fwrite(STDERR, "BATCH_SIZE must be between 1 and 50000.\n");
    exit(1);
}
if (!function_exists('pcntl_fork')) {
    fwrite(STDERR, "pcntl_fork is required. Enable the pcntl extension.\n");
    exit(1);
}
set_time_limit(0);
ini_set('memory_limit', '-1');

// ---- Faker or fallback ----
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
$FIRST_NAMES = ['Alex','Sam','Taylor','Jordan','Casey','Riley','Morgan','Jamie','Cameron','Avery','Drew','Quinn','Reese','Rowan','Skyler'];
$LAST_NAMES  = ['Smith','Johnson','Williams','Brown','Jones','Miller','Davis','Garcia','Rodriguez','Wilson','Martinez','Anderson','Taylor','Thomas'];
$CITIES      = ['New York','London','Berlin','Paris','Madrid','Rome','Toronto','Sydney','Tokyo','Seoul','Singapore','Dubai','Sao Paulo','Mexico City'];
$COUNTRIES   = ['United States','United Kingdom','Germany','France','Spain','Italy','Canada','Australia','Japan','South Korea','Singapore','United Arab Emirates','Brazil','Mexico'];

$randName = static function () use ($fakerAvailable, $faker, $FIRST_NAMES, $LAST_NAMES): string {
    if ($fakerAvailable) return $faker->name();
    return $FIRST_NAMES[array_rand($FIRST_NAMES)] . ' ' . $LAST_NAMES[array_rand($LAST_NAMES)];
};
$randCity = static function () use ($fakerAvailable, $faker, $CITIES): string {
    if ($fakerAvailable) return $faker->city();
    return $CITIES[array_rand($CITIES)];
};
$randCountry = static function () use ($fakerAvailable, $faker, $COUNTRIES): string {
    if ($fakerAvailable) return $faker->country();
    return $COUNTRIES[array_rand($COUNTRIES)];
};
$randDob = static function (): string {
    $start = strtotime('1950-01-01');
    $end   = strtotime('2010-12-31');
    $ts    = random_int($start, $end);
    return date('Y-m-d', $ts);
};
$randSex = static function (): string {
    return (random_int(0, 1) === 1) ? 'M' : 'F';
};

// ---- Forking logic ----
$rowsPerProcess = (int)ceil($TOTAL_ROWS / $NUM_PROCESSES);
$startTime = microtime(true);

echo "Seeding {$TOTAL_ROWS} rows into {$DB_NAME}.{$DB_TABLE} using {$NUM_PROCESSES} processes (batch size: {$BATCH_SIZE})...\n";

$pids = [];
for ($proc = 0; $proc < $NUM_PROCESSES; $proc++) {
    $pid = pcntl_fork();
    if ($pid === -1) {
        fwrite(STDERR, "Could not fork process.\n");
        exit(1);
    }
    if ($pid === 0) {
        // ---- Child process ----
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
        // Optionally disable checks
        if ($DISABLE_CHECKS) {
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                $pdo->exec('SET UNIQUE_CHECKS=0');
            } catch (Throwable $e) {}
        }
        $columns = ['Name','Dob','Email','City','Country','Sex'];
        $startRow = $proc * $rowsPerProcess;
        $endRow = min($TOTAL_ROWS, ($proc + 1) * $rowsPerProcess);
        $inserted = 0;
        for ($row = $startRow; $row < $endRow; $row += $BATCH_SIZE) {
            $rowsThisBatch = min($BATCH_SIZE, $endRow - $row);
            $placeholders = [];
            $values = [];
            for ($i = 0; $i < $rowsThisBatch; $i++) {
                $globalIndex = $row + $i + 1;
                $name = $randName();
                $dob = $randDob();
                $email = "user{$globalIndex}@example.com";
                $city = $randCity();
                $country = $randCountry();
                $sex = $randSex();
                $placeholders[] = '(?, ?, ?, ?, ?, ?)';
                array_push($values, $name, $dob, $email, $city, $country, $sex);
            }
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $pdo->beginTransaction();
            try {
                $sql = "INSERT INTO `{$DB_TABLE}` (`Name`, `Dob`, `Email`, `City`, `Country`, `Sex`) VALUES " . implode(',', $placeholders);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                fwrite(STDERR, "Process {$proc}: Batch failed: " . $e->getMessage() . PHP_EOL);
                exit(1);
            }
            $inserted += $rowsThisBatch;
            if ($inserted % 100_000 === 0 || $row + $rowsThisBatch >= $endRow) {
                $elapsed = microtime(true) - $startTime;
                $rate = $elapsed > 0 ? number_format(($row + $rowsThisBatch - $startRow) / $elapsed, 0) : 'n/a';
                echo "Process {$proc}: Inserted " . ($row + $rowsThisBatch - $startRow) . " rows (≈{$rate} rows/s)\n";
            }
        }
        // Re-enable checks
        if ($DISABLE_CHECKS) {
            try {
                $pdo->exec('SET UNIQUE_CHECKS=1');
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable $e) {
                fwrite(STDERR, "Process {$proc}: Warning: could not re-enable checks: " . $e->getMessage() . PHP_EOL);
            }
        }
        $elapsed = microtime(true) - $startTime;
        echo "Process {$proc}: Done. Inserted " . ($endRow - $startRow) . " rows in " . number_format($elapsed, 2) . "s\n";
        //exit(0);
    } else {
        $pids[] = $pid;
    }
}
print "come here\n";
// ---- Parent waits for children ----
foreach ($pids as $pid) {
    print "child won't wait for {$pid}\n";
    pcntl_waitpid($pid, $status);
}
$elapsed = microtime(true) - $startTime;
echo "All done. Inserted {$TOTAL_ROWS} rows in " . number_format($elapsed, 2) . "s\n";