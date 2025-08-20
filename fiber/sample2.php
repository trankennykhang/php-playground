<?php

$ffmpeg = getenv('FFMPEG_BIN') ?: 'ffmpeg';
$concurrency = $argv[1] ?? 3;
if ($item->getExtension() === 'mkv'){
$fiber = new Fiber(createVideoClip(...));
$fiber->start($ffmpeg, $item->getPathname(), getTempDestination());
$fiberList[] = $fiber;
if (count($fiberList) >= $concurrency){
foreach (waitForFibers($fiberList, 1) as $fiber){
[$source, $destination] = $fiber->getReturn();
echo 'Success
$fiberList = [];
$start = microtime(true);

foreach (new DirectoryIterator('.') as $item){fully created clip from ' . $source . ' => ' . $destination . PHP_EOL;
}
}
}
}

foreach (waitForFibers($fiberList) as $fiber){
[$source, $destination] = $fiber->getReturn();
echo 'Successfully created clip from ' . $source . ' => ' . $destination . PHP_EOL;
}

$end = microtime(true);
echo 'Directory processed in ' . round($end - $start, 1) . ' seconds' . PHP_EOL;

/**
* @param Fiber[] $fiberList
* @param int|null $completionCount
*
* @return Fiber[]
*/
function waitForFibers(array &$fiberList, ?int $completionCount = null) : array{
$completedFibers = [];
$completionCount ??= count($fiberList);
while (count($fiberList) && count($completedFibers) < $completionCount){
usleep(1000);
foreach ($fiberList as $idx => $fiber){
if ($fiber->isSuspended()){
$fiber->resume();
} else if ($fiber->isTerminated()){
$completedFibers[] = $fiber;
unset($fiberList[$idx]);
}
}
}

return $completedFibers;
}

function getTempDestination() : string{
$destination = tempnam(sys_get_temp_dir(), 'video');
unlink($destination);
$dir = dirname($destination);
$file = basename($destination, '.tmp');

return $dir . DIRECTORY_SEPARATOR . $file . '.mp4';
}

function createVideoClip(string $ffmpeg, string $source, string $destination) : array{
$cmd = sprintf('%s -threads 1 -i %s -t 30 -crf 26 -c:v h264 -c:a ac3 %s', $ffmpeg, $source, $destination);

$stdout = fopen('php://temporary', 'w+');
$stderr = fopen('php://temporary', 'w+');
$streams = [
0 => ['pipe', 'r']
, 1 => $stdout
, 2 => $stderr
];

$proc = proc_open($cmd, $streams, $pipes);
if (!$proc){
throw new \RuntimeException('Unable to launch download process');
}

do {
Fiber::suspend();
$status = proc_get_status($proc);
} while ($status['running']);

proc_close($proc);
fclose($stdout);
fclose($stderr);
$success = $status['exitcode'] === 0;
if ($success){
return [$source, $destination];
} else {
throw new \RuntimeException('Unable to perform conversion');
}
}