<?php
declare(strict_types=1);
namespace ff\route;

use function ff\{from, input, output};
/**
 * RESTful 控制器编排。基于 route() 的子路由映射，接收 handlers 映射自动展开为标准 route() 键。
 * ```
 * return rest([
 *     'list'    => fn($input) => [users()->list($input)],              // get:
 *     'create'  => fn($input) => [users()->create($input)->save(), 201],// post:
 *     'get'     => fn($input) => [users()->findByID($input['id'])?->output()], // get:/{id}
 *     'update'  => fn($input) => [null, ... ? 204 : 404],                // patch:/{id}
 *     'replace' => fn($input) => [users()->replace($input['id'], $input)],// put:/{id}
 *     'delete'  => fn($input) => [null, ... ? 204 : 404],                // delete:/{id}
 * ], '{id}', [
 *     'list'   => ['name' => 'query,str', 'email' => 'query,email'],
 *     'create' => ['name' => 'str', 'email' => 'email'],
 *     'update' => ['name' => 'str'],
 * ]);
 * ```
 * @param array  $handlers handlers 映射，key 为语义名，value 为业务闭包 fn(array $input): array
 * @param string $param    URL 参数占位符，默认 '{id}'
 * @param array  $rules    input 规则映射，key 与 handlers 对应，value 直接传入 input($value)
 * @return array   route() 子路由映射数组
 */
function rest(array $handlers, string $param = '{id}', array $rules = []): array{
	$paramName = trim($param, '{}');
	$needsParam = $paramName !== '' ? ['get' => 1, 'update' => 1, 'replace' => 1, 'delete' => 1] : [];
	$map = [
		'list' => 'get:',
		'create' => 'post:',
		'get' => "get:/$param",
		'update' => "patch:/$param",
		'replace' => "put:/$param",
		'delete' => "delete:/$param",
	];
	$result = [];
	foreach($handlers as $key => $handler){
		$routeKey = $map[$key] ?? null;
		if(!$routeKey) continue;
		$result[$routeKey] = function() use ($handler, $rules, $needsParam, $key, $paramName){
			$handlerRules = $rules[$key] ?? [];
			$input = $handlerRules ? (array)input($handlerRules) : [];
			if(($needsParam[$key] ?? 0) && !array_key_exists($paramName, $input)) $input[$paramName] = from($paramName, 'params');
			return output(...$handler($input));
		};
	}
	return $result;
}
