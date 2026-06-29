<?php
declare(strict_types=1);
namespace ff\output\type;
/**
 * @param array $response
 * @return array
 * @internal
 */
function view(array $response): array{
	ob_start();
	extract($response['body'] ?? []);
	include $response['file'];
	$response['body'] = ob_get_clean();
	return $response;
}
