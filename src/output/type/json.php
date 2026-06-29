<?php
declare(strict_types=1);
namespace ff\output\type;
/**
 * @param array $response
 * @return array
 * @internal
 */
function json(array $response): array{
	if(null !== ($response['body'] ?? null)){
		$response['headers'] = [...($response['headers'] ?? []), 'Content-Type' => 'application/json; charset=UTF-8'];
		$options = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		if($response['pretty'] ?? false) $options |= JSON_PRETTY_PRINT;
		try{
			$response['body'] = json_encode($response['body'], $options);
		}catch(\JsonException $e){
			$response['code'] = 500;
			$response['message'] = $e->getMessage();
		}
	}
	return $response;
}
