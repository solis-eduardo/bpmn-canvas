// Prints PHP version + loaded extensions of the php-wasm 8.4 build, and checks
// the extensions Laravel needs. Run: node check-extensions.mjs
import { PHP } from '@php-wasm/universal';
import { loadNodeRuntime } from '@php-wasm/node';

const php = new PHP(await loadNodeRuntime('8.4', { emscriptenOptions: { processId: 1 } }));
const res = await php.run({
  code: `<?php
echo PHP_VERSION, "\\n";
$need = ['mbstring','openssl','pdo','pdo_sqlite','sqlite3','ctype','tokenizer','fileinfo','json','xml','dom','simplexml','xmlwriter','libxml','curl','filter','hash','iconv','session','zip','zlib','bcmath','intl','pcntl','posix','gd','sodium','pcre','random','readline','phar','proc_open'];
foreach ($need as $e) echo str_pad($e, 12), (extension_loaded($e) ? 'yes' : 'NO'), "\\n";
echo "proc_open fn: ", var_export(function_exists('proc_open'), true), "\\n";
echo "all: ", implode(',', get_loaded_extensions()), "\\n";
`,
});
console.log(res.text);
process.exit(0);
