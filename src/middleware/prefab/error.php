<?php
namespace nx\middleware\prefab;

use function nx\{container, i18n, output};

/**
 * 统一异常处理中间件
 * 使用方式:
 * ```
 * middleware(error(), $handler);                              // 默认 500，无 body
 * middleware(error([\InvalidArgumentException::class => 400]), $handler); // int：仅状态码
 * middleware(error([\RuntimeException::class => [500, '#error:msg']]), $handler);// [code, msg]：i18n(msg) + 状态码
 * middleware(error([\DomainException::class => [422, null, 'en_US']]), $handler);// 指定语言
 * ```
 * 未设消息时回退到 `container("#error:$code")`，存在则 i18n 翻译，不存在则无 body。
 * i18n 上下文参数：`{status}`、`{code}`、`{message}`、`{file}`、`{line}`
 * @param array $statusMap 异常类名 => int|[int,?string,?string] 的映射
 * @return callable 中间件函数
 */
function error(array $statusMap = []): callable{
	return function($next) use ($statusMap){
		try{
			return $next();
		}catch(\Throwable $e){
			$config = $statusMap[$e::class] ?? [500, null, null];
			if(!is_array($config)) $config = [$config, null, null];
			[$code, $message, $lang] = $config + [500, null, null];
			$msg = $message ?? container("#error:$code") ?? null;
			return output($msg ? ['error' => i18n($msg, ['status' => $code, 'code' => $e->getCode(), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], $lang)] : null, $code);
		}
	};
}
