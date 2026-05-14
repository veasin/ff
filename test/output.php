<?php
include __DIR__ . "/../vendor/autoload.php";

use function nx\{container, output, test};

// 模拟容器配置
//container('#out.formats', [
//	'json' => fn($data) => json_encode($data),
//	'html' => fn($data) => "<html><body>" . htmlspecialchars($data) . "</body></html>"
//]);
container('#out.formats.html', function($data){
	echo "<html><body>" . htmlspecialchars($data['body']) . "</body></html>";
});
// 测试 JSON 输出
output(['name' => 'test'], 'json');
ob_start();
output();
$result = ob_get_clean();
$expected = json_encode(['name' => 'test']);
test('JSON 输出', $result, $expected);
// 测试 HTML 输出
output('hello world', 'html');
ob_start();
output();
$result = ob_get_clean();
$expected = "<html><body>hello world</body></html>";
test('HTML 输出', $result, $expected);
// 测试状态码输出
output(['error' => 'not found'], 404);
ob_start();
output();
$result = ob_get_clean();
// 状态码测试需要在CLI下验证，这里仅检查内容
test('状态码输出', $result, fn($result) => strpos($result, 'error') !== false);
// 测试无body状态码
output(null, 204);
ob_start();
output();
$result = ob_get_clean();
test('204无内容输出', $result, '');
// 测试 output() 无参触发发送
output(['name' => 'test'], 'json');
ob_start();
output();
$result = ob_get_clean();
test('output() 无参发送', $result, json_encode(['name' => 'test']));
test();

