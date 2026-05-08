<?php
namespace nx\output;
/**
 * @param $response
 * @param $formats
 * @return void
 * @internal
 */
function view($response, $formats): void{
	ob_start();
	extract($response['body'] ?? []);
	include $response['file'];
	$response['body'] = ob_get_clean();
	$formats['http']($response);
}