<?php
$srcDir = __DIR__ . '/../src';
$outputFile = __DIR__ . '/../dist/nx.php';

include $outputFile;

function loadNX(){}

echo "\n";
$testDir = __DIR__ . '/../test';
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
$files =[...$files];
foreach ($files as $file) {
    if ($file->getExtension() !== 'php' || $file->getBasename()[0] === '_') continue;
    $rel = $file->getPathname();
	$pathname =str_replace($testDir, '', $rel);
    echo "\033[36m  RUN\033[0m $pathname\n";
    //passthru('php -d auto_prepend_file=' . __DIR__ . '/dist/nx.php ' . escapeshellarg($rel), $code);
	try{
		\nx\container(null);
		\nx\container('#in', null);
		\nx\container('#out', null);
		include $rel;
	} catch(Throwable $e){
		$msg = $e->getMessage();
		echo "  \033[31mFAIL\033[0m ($msg)\n";
		\nx\test();
	}
    //if ($code !== 0) echo "  \033[31mFAIL\033[0m (exit code: $code)\n";
}
echo "\033[32mALL TESTS DONE\033[0m\n";
