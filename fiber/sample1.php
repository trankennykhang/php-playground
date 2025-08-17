<?php
/**
 * File: sample1.php
 * Read multiple files and calculate the hash value
 * Try to figure out how the fiber handles non-blocking I/O
 */
/*
 * Fiber function
 */
function read_file(string $file) {
    Fiber::suspend();
    if (file_exists($file)) {
        echo "File existed: prepare to read {$file}\n";
        // Example work:
        // $hash = hash_file('sha256', $file);
        // echo "Hash: $hash\n";
    } else {
        echo "File not found: $file\n";
    }

}
$fibers = [];

// Pass a callable to Fiber; don't call the function here
$fibers[] = new Fiber(fn() => read_file("data/file2.txt"));
$fibers[] = new Fiber(fn() => read_file("data/file3.txt"));


foreach ($fibers as $fiber) {
    $fiber->start();
}
foreach ($fibers as $fiber) {
    if ($fiber->isSuspended()) {
        $fiber->resume();
    }

}
