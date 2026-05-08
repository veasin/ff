<?php
namespace nx\output;
/**
 * @param $response
 * @param $formats
 * @return void
 * @internal
 */
function file($response, $formats): void{
	$path = $response['file'] ?? null;
	if(!$path || !file_exists($path)){
		$response['code'] = 404;
		$response['body'] = '';
		$formats['http']($response);
		return;
	}
	$download = $response['body'] ?? false;
	$response['body'] = file_get_contents($path);
	$response['headers'] = [...($response['headers'] ?? [])];
	$mime = mime_content_type($path);
	if($mime) $response['headers']['Content-Type'] = $mime;
	if($download){
		$filename = basename($path);
		$response['headers']['Content-Disposition'] = 'attachment; filename="' . $filename . '"';
		$response['headers']['Content-Length'] = filesize($path);
	}
	$formats['http']($response);
}