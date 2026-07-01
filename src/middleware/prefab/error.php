<?php
namespace ff\middleware\prefab;

use function ff\{container, i18n, output};

/**
 * 统一异常处理中间件
 * 使用方式:
 * ```
 * middleware(error(), $handler);                              // 默认 500，HTTP 状态描述为空
 * middleware(error([\InvalidArgumentException::class => 400]), $handler); // int：仅状态码
 * middleware(error([\RuntimeException::class => [500, '#error:msg']]), $handler);// [code, msg]：i18n(msg) 设为 HTTP 状态描述
 * middleware(error([\DomainException::class => [422, null, 'en_US']]), $handler);// 指定语言
 * ```
 * 消息通过 `output(..., ['message' => ...])` 设为 HTTP 状态描述（如 `400 Invalid argument`），而非 body。
 * 未设消息时回退到 `container("#mw/error/$code")`，存在则 i18n 翻译，不存在则 HTTP 状态描述为空。
 * i18n 上下文参数：`{status}`、`{code}`、`{message}`、`{file}`、`{line}`（`{message}` 直接替换为 $e->getMessage()）
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
			return output(null, [
				'code' => (int)$code,
				'message' => i18n($message ?? container("#mw/error/$code") ?? '', [
					'status' => $code,
					'code' => $e->getCode(),
					'message' => $e->getMessage(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				], $lang),
			]);
		}
	};
}
