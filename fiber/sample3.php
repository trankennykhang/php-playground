<?php

// A simple, pure PHP event loop. In a real application, this would be more robust.
// For this example, it's just a way to manage our asynchronous tasks.
class SimpleEventLoop
{
    private array $pendingFibers = [];

    public function addFiber(Fiber $fiber): void
    {
        $this->pendingFibers[] = $fiber;
    }

    public function run(): void
    {
        while (!empty($this->pendingFibers)) {
            $fiber = array_shift($this->pendingFibers);

            try {
                // Resume the fiber. It will suspend itself again if the task is not complete.
                $fiber->resume();
            } catch (Throwable $e) {
                echo "Fiber error: " . $e->getMessage() . "\n";
            }
        }
    }
}

// A simple helper function to perform an asynchronous mysqli query inside a Fiber.
function asyncQuery(mysqli $db, string $sql): array
{
    echo "Fiber [".spl_object_id(Fiber::getCurrent())."]: Starting async query...\n";

    // Send the query to the server without waiting for a result.
    // This is the key non-blocking step.
    $db->query($sql, MYSQLI_ASYNC);

    // The current Fiber suspends itself, yielding control back to the event loop.
    // The event loop will resume it later.
    Fiber::suspend();

    // The Fiber has been resumed. Let's check the result.
    echo "Fiber [".spl_object_id(Fiber::getCurrent())."]: Resumed. Fetching result...\n";

    $result = $db->reap_async_query();

    if ($result === false) {
        throw new Exception("Query failed: " . $db->error);
    }

    if ($result instanceof mysqli_result) {
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $result->free();
        return $data;
    }

    return [];
}

// Our main function that will be run inside a Fiber.
function main(): Generator
{
    $eventLoop = new SimpleEventLoop();

    // The key here is to create two separate fibers to handle the queries.
    // Each Fiber is its own stack and can be suspended and resumed independently.
    $fiber1 = new Fiber(function () use ($eventLoop) {
        echo "Fiber 1 started.\n";
        $db = new mysqli('127.0.0.1', 'root', '', 'test');
        // Tell the event loop to check on this fiber later.
        $eventLoop->addFiber(Fiber::getCurrent());
        $result = yield from asyncQuery($db, "SELECT 'User 1' AS name, 1 AS id");
        echo "Fiber 1 finished. Result: " . json_encode($result) . "\n";
    });

    $fiber2 = new Fiber(function () use ($eventLoop) {
        echo "Fiber 2 started.\n";
        $db = new mysqli('127.0.0.1', 'root', '', 'test');
        // Simulate a delay with a non-blocking sleep.
        $start = microtime(true);
        while (microtime(true) - $start < 0.5) { // wait for 500ms
            Fiber::suspend();
        }
        $eventLoop->addFiber(Fiber::getCurrent());
        $result = yield from asyncQuery($db, "SELECT 'User 2' AS name, 2 AS id");
        echo "Fiber 2 finished. Result: " . json_encode($result) . "\n";
    });

    // Start both fibers. They will run until their first suspend.
    $fiber1->start();
    $fiber2->start();

    // Now, run our event loop.
    // The event loop's job is to check for completed queries and resume the correct fibers.
    while ($eventLoop->hasPendingQueries()) {
        $reads = $writes = $errors = [];
        foreach ($eventLoop->getPendingFibers() as $fiber) {
            // Get the database link from the fiber. (This is a simplified approach)
            // In a real system, you would manage these links more robustly.
            $reads[] = $fiber->getDBLink();
        }

        if (mysqli_poll($reads, $writes, $errors, 0, 100000)) {
            // A query has completed.
            foreach ($reads as $dbLink) {
                // Find the fiber associated with this completed query.
                $fiber = $eventLoop->findFiberForDBLink($dbLink);
                if ($fiber) {
                    $fiber->resume(); // Resume the fiber.
                }
            }
        }
    }
}

// In a real application, you would wrap this in a more elegant way.
// For this example, we'll manually set up and run the loop.

// This is the core "event loop" logic for our example.
// It will be very simple.
function runEventLoop(): void
{
    $fibers = [];

    // We'll use this array to manage our non-blocking operations.
    // Key: mysqli link, Value: Fiber instance
    $asyncOperations = new SplObjectStorage();

    $fiber1 = new Fiber(function() use (&$asyncOperations) {
        $db = new mysqli('127.0.0.1', 'root', '', 'test');
        $db->query("SELECT 'User 1' AS name, 1 AS id", MYSQLI_ASYNC);
        $asyncOperations[$db] = Fiber::getCurrent();
        Fiber::suspend(); // Yield control

        $result = $db->reap_async_query();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo "Fiber 1 result: " . json_encode($data) . "\n";
        $result->free();
    });

    $fiber2 = new Fiber(function() use (&$asyncOperations) {
        $db = new mysqli('127.0.0.1', 'root', '', 'test');
        // Simulate a longer-running task by introducing a delay
        $db->query("SELECT 'User 2' AS name, 2 AS id, SLEEP(1) as delay", MYSQLI_ASYNC);
        $asyncOperations[$db] = Fiber::getCurrent();
        Fiber::suspend(); // Yield control

        $result = $db->reap_async_query();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo "Fiber 2 result: " . json_encode($data) . "\n";
        $result->free();
    });

    $fibers[] = $fiber1;
    $fibers[] = $fiber2;

    foreach ($fibers as $fiber) {
        $fiber->start();
    }

    // The main event loop
    while (!$asyncOperations->isEmpty()) {
        $read = $write = $error = [];
        foreach ($asyncOperations as $db) {
            $read[] = $db;
        }

        // mysqli_poll checks for completed queries on the given links.
        // It's a blocking call, but it's efficient and can wait on multiple links at once.
        $numReady = mysqli_poll($read, $write, $error, 1); // 1-second timeout

        if ($numReady > 0) {
            foreach ($read as $dbReady) {
                // Find the Fiber corresponding to the database link
                if ($asyncOperations->contains($dbReady)) {
                    $fiber = $asyncOperations[$dbReady];
                    $fiber->resume(); // Resume the suspended Fiber
                    $asyncOperations->detach($dbReady); // Remove it from our list
                }
            }
        }
    }

    echo "All non-blocking operations are complete.\n";
}

// Start the whole process
runEventLoop();

?>