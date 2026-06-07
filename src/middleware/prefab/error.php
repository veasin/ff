<?php
namespace nx\middleware\prefab;
use function nx\{from, container, output, i18n};

/**
 * 统一异常处理中间件
 * 
 * 使用方式:
 * - 调试模式: middleware(error(debug: true), $handler) - 显示完整错误信息
 * - 生产模式: middleware(error(), $handler) - 返回通用错误消息
 * 
 * @param bool $debug 是否开启调试模式，默认 false
 * @return callable 中间件函数
 */
function error(bool $debug = false): callable{
	return function($next) use ($debug){
		try{
			return $next();
		}catch(\Throwable $e){
			$body = $debug ? [
				'error' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'trace' => $e->getTraceAsString(),
			] : ['error' => i18n('#error:internal')];
			if($debug) $body['type'] = get_class($e);
			return output($body, $debug ? 500 : ($e->getCode() ?: 500));
		}
	};
}
