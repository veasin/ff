<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, ext, test};

container('^#ext.test.h1', fn($x) => "h1:$x");
container('^#ext.test.h2', fn($x) => "h2:$x");
container('^#ext.test.h3', fn($x) => null);

test('string mode', ext('test', 'h1', 'a'), 'h1:a');
test('string mode nonexistent', ext('test', 'nx', 'a'), null);

$r = ext('test', true, 'a');
test('true mode count', count($r), 3);
test('true mode h2', $r['h2'], 'h2:a');
test('true mode h3 null', $r['h3'], null);

test('false mode', ext('test', false, 'a'), null);

test('null mode first', ext('test', null, 'a'), 'h1:a');

container('^#ext.testB.h1', fn($x) => null);
container('^#ext.testB.h2', fn($x) => "h2:$x");
container('^#ext.testB.h3', fn($x) => null);
test('null mode fallthrough', ext('testB', null, 'a'), 'h2:a');

container('^#ext.testC.h1', fn($x) => null);
container('^#ext.testC.h2', fn($x) => null);
test('null mode all null', ext('testC', null, 'a'), null);

container('^#ext.testD.h1', fn($x) => "h1:$x");
container('^#ext.testD.h2', fn($x) => null);
container('^#ext.testD.h3', fn($x) => "h3:$x");
test('array mode first', ext('testD', ['h2', 'h1', 'h3'], 'a'), 'h1:a');
test('array mode fallthrough', ext('testD', ['h2', 'h3'], 'a'), 'h3:a');
test('array mode all null', ext('testD', ['h2'], 'a'), null);
test('array mode nonexistent', ext('testD', ['nx'], 'a'), null);

test('empty domain', ext('noexist', 'h1', 'a'), null);

container('^#ext.testE.h1', 'not_callable');
container('^#ext.testE.h2', fn($x) => "h2:$x");
test('non-callable skipped', ext('testE', null, 'a'), 'h2:a');
test('string mode non-callable', ext('testE', 'h1', 'a'), null);

test();

container(null, true);
